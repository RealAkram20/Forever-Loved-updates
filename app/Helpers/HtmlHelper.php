<?php

namespace App\Helpers;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitises the rich text our editors produce (Quill) before it is printed with `{!! !!}`.
 *
 * This used to be a one-line `strip_tags($html, $allowed)`. strip_tags filters *tags* and
 * nothing else — every attribute on a surviving tag is passed through verbatim — so with
 * `img`, `a`, `div` and `span` on the allow-list, `<img src=x onerror=...>` and
 * `<a href="javascript:...">` went straight to the page. Tributes are writable by
 * unauthenticated visitors and rendered to the memorial's owner and to platform admins,
 * which made that a stored XSS anyone could reach.
 *
 * So the parse is real: build a DOM, keep only known-good elements, and on each of those
 * keep only known-good attributes whose values survive a scheme check. Anything unrecognised
 * is dropped rather than escaped-and-shown, because the input is authored HTML, not text.
 */
class HtmlHelper
{
    /** Elements that may appear in the output at all. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'blockquote', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'span', 'div', 'img', 'pre', 'code',
    ];

    /**
     * Attributes kept per element. Everything absent from this map goes, which is what
     * closes the whole `on*` event-handler family without having to enumerate it — and
     * keeps closing it as browsers add new handlers.
     *
     * `style` is deliberately not allowed anywhere: it is the one attribute that can
     * reposition an element over the rest of the page, and Quill's own formatting
     * (alignment, indent) travels in `class` instead.
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        '*' => ['class'],
    ];

    /** URL schemes permitted in href/src. Notably absent: javascript:, vbscript:, file:. */
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Elements removed *including their contents*. For everything else not on the tag
     * allow-list we unwrap — keeping the text — but the text inside these is code, and
     * lifting it into the document is exactly the injection we are preventing.
     */
    private const STRIPPED_SUBTREES = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet', 'form', 'input',
        'button', 'select', 'option', 'textarea', 'svg', 'math', 'template',
        'noscript', 'base', 'link', 'meta', 'frame', 'frameset', 'audio', 'video',
    ];

    /** Class names kept on any element: Quill's own formatting classes and nothing else. */
    private const ALLOWED_CLASS_PATTERN = '/^ql-[a-z0-9\-]+$/i';

    public static function sanitize(?string $html): string
    {
        return self::clean($html, self::ALLOWED_TAGS);
    }

    /**
     * Same engine, caller-supplied tag allow-list — so the page builder's narrower
     * paragraph rules go through one implementation rather than a second regex pass that
     * has to re-derive which attributes are dangerous.
     *
     * @param  string[]  $allowedTags
     */
    public static function clean(?string $html, array $allowedTags): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $allowed = array_map('strtolower', $allowedTags);

        $doc = new DOMDocument('1.0', 'UTF-8');

        // loadHTML assumes ISO-8859-1 when the markup carries no charset declaration, which
        // mangles every accented name in a tribute. Pre-encoding non-ASCII as numeric
        // entities is the supported way to say "this is UTF-8" now that passing
        // 'HTML-ENTITIES' to mb_convert_encoding is deprecated (PHP 8.2).
        $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<div id="__sanitize_root__">'.$encoded.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            // Unparseable markup is not worth guessing at — fall back to plain text.
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        $root = $doc->getElementById('__sanitize_root__') ?? $doc->documentElement;

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::cleanChildren($root, $allowed);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Walks a snapshot of the child list, because sanitising a node can replace or remove
     * it — iterating the live DOMNodeList while mutating it silently skips siblings.
     *
     * @param  string[]  $allowed
     */
    private static function cleanChildren(DOMNode $parent, array $allowed): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            self::cleanNode($child, $allowed);
        }
    }

    /**
     * @param  string[]  $allowed
     */
    private static function cleanNode(DOMNode $node, array $allowed): void
    {
        // Text is kept as-is; saveHTML escapes it on the way out. Comments go, because a
        // conditional comment is executable markup on old engines and they carry nothing
        // a reader needs.
        if ($node->nodeType === XML_TEXT_NODE) {
            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE || ! $node instanceof DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::STRIPPED_SUBTREES, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        // Descend first: unwrapping below would otherwise move unvisited children up into
        // a parent this loop has already passed.
        self::cleanChildren($node, $allowed);

        if (! in_array($tag, $allowed, true)) {
            self::unwrap($node);

            return;
        }

        self::cleanAttributes($node, $tag);
    }

    /** Replaces an element with its children, keeping the text of an unknown wrapper. */
    private static function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $parent->insertBefore($child, $node);
        }

        $parent->removeChild($node);
    }

    private static function cleanAttributes(DOMElement $node, string $tag): void
    {
        $permitted = array_merge(
            self::ALLOWED_ATTRIBUTES['*'] ?? [],
            self::ALLOWED_ATTRIBUTES[$tag] ?? [],
        );

        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $permitted, true)) {
                $node->removeAttribute($attribute->nodeName);

                continue;
            }

            $value = $attribute->nodeValue ?? '';

            if (in_array($name, ['href', 'src'], true) && ! self::isSafeUrl($value)) {
                $node->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'class') {
                $kept = array_filter(
                    preg_split('/\s+/', trim($value)) ?: [],
                    fn (string $class) => $class !== '' && preg_match(self::ALLOWED_CLASS_PATTERN, $class) === 1,
                );

                $kept === []
                    ? $node->removeAttribute('class')
                    : $node->setAttribute('class', implode(' ', $kept));
            }
        }

        // A link that opens a new tab hands the opener to the destination unless told not
        // to. Set rather than validated, since the editor never has a reason to choose.
        if ($tag === 'a' && $node->hasAttribute('href')) {
            if ($node->getAttribute('target') !== '') {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
            } else {
                $node->setAttribute('rel', 'nofollow noopener');
            }
        }
    }

    /**
     * Relative and anchor URLs pass; absolute ones must name an allowed scheme. Inline
     * images are allowed only for real raster types — `data:image/svg+xml` carries markup.
     */
    private static function isSafeUrl(string $url): bool
    {
        // Control characters and entity padding are how `java\tscript:` slips past a naive
        // prefix check; browsers strip them before resolving, so we do too.
        $candidate = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES, 'UTF-8')) ?? '');

        if ($candidate === '') {
            return false;
        }

        if (str_starts_with($candidate, 'data:')) {
            return (bool) preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$#i', $candidate);
        }

        // No scheme at all: a relative path, a fragment, or a protocol-relative URL. The
        // first two are fine; `//evil.com` is treated as absolute and must be http(s).
        if (! preg_match('#^([a-z][a-z0-9+.\-]*):#', $candidate, $matches)) {
            return ! str_starts_with($candidate, '//');
        }

        return in_array($matches[1], self::ALLOWED_URL_SCHEMES, true);
    }
}
