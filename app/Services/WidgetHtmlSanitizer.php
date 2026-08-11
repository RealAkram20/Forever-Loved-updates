<?php

namespace App\Services;

use App\Helpers\HtmlHelper;

class WidgetHtmlSanitizer
{
    private const PARAGRAPH_ALLOWED = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'blockquote', 'span', 'pre', 'code',
    ];

    /**
     * Delegates to the shared DOM sanitiser rather than keeping its own pass.
     *
     * The previous implementation was strip_tags plus a regex that deleted `\son\w+=`.
     * Both halves leaked: strip_tags kept every attribute on the tags it allowed, and the
     * regex required whitespace before the handler — but HTML needs none after a quoted
     * value, so `<a href="x"onmouseover="...">` sailed through. It also had no opinion at
     * all about `href="javascript:..."`. One engine, one place to get this right.
     */
    public static function paragraph(string $html): string
    {
        return HtmlHelper::clean($html, self::PARAGRAPH_ALLOWED);
    }

    /**
     * Allow a single iframe with http(s) URL, or empty string if invalid.
     */
    public static function embedHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '' || ! preg_match('/<iframe\b/i', $html)) {
            return '';
        }

        $src = '';
        if (preg_match('/src\s*=\s*("|\')([^"\']+)\1/i', $html, $m)) {
            $src = $m[2];
        } elseif (preg_match('/src\s*=\s*([^\s>]+)/i', $html, $m)) {
            $src = $m[1];
        }
        if ($src === '' || ! self::isAllowedIframeUrl($src)) {
            return '';
        }

        $title = 'Embedded content';
        if (preg_match('/title\s*=\s*("|\')([^"\']*)\1/i', $html, $tm)) {
            $title = $tm[2] !== '' ? $tm[2] : $title;
        }

        $width = '100%';
        $height = '400';
        if (preg_match('/width\s*=\s*("|\')([^"\']+)\1/i', $html, $wm)) {
            $width = $wm[2];
        }
        if (preg_match('/height\s*=\s*("|\')([^"\']+)\1/i', $html, $hm)) {
            $height = $hm[2];
        }

        $srcEsc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $widthEsc = htmlspecialchars((string) $width, ENT_QUOTES, 'UTF-8');
        $heightEsc = htmlspecialchars((string) $height, ENT_QUOTES, 'UTF-8');

        return '<iframe src="'.$srcEsc.'" width="'.$widthEsc.'" height="'.$heightEsc.'" title="'.$titleEsc.'" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen class="max-w-full rounded-lg border border-gray-200 dark:border-gray-700"></iframe>';
    }

    public static function isAllowedIframeUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['https', 'http'], true);
    }
}
