<?php

namespace App\Services;

class WidgetHtmlSanitizer
{
    private const PARAGRAPH_ALLOWED = '<p><br><br/><strong><b><em><i><u><a><ul><ol><li><h1><h2><h3><h4><blockquote><span><pre><code>';

    public static function paragraph(string $html): string
    {
        $clean = strip_tags($html, self::PARAGRAPH_ALLOWED);
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;

        return $clean;
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
