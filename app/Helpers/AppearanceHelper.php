<?php

namespace App\Helpers;

use App\Models\SystemSetting;

class AppearanceHelper
{
    /**
     * Family names are interpolated into a stylesheet URL and a <style> block,
     * so anything but a plain font name must never get through.
     */
    public static function sanitizeFamily(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim($value);

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{0,80}$/', $value) ? $value : '';
    }

    /** All Google font family names from the curated catalogue (flat). */
    public static function googleFamilies(): array
    {
        return array_merge(...array_values(config('google-fonts', [])));
    }

    /**
     * Uploaded fonts: [{name, path, format}, ...] — entries whose file is gone
     * are filtered out so a missing font can never break the stylesheet.
     */
    public static function customFonts(): array
    {
        $fonts = SystemSetting::get('appearance.custom_fonts', []);
        if (! is_array($fonts)) {
            return [];
        }

        return array_values(array_filter($fonts, function ($f) {
            return is_array($f)
                && self::sanitizeFamily($f['name'] ?? null) !== ''
                && ! empty($f['path'])
                && \Illuminate\Support\Facades\Storage::disk('public')->exists($f['path']);
        }));
    }

    public static function bodyFont(): string
    {
        return self::sanitizeFamily(SystemSetting::get('appearance.font_body', ''));
    }

    public static function headingFont(): string
    {
        return self::sanitizeFamily(SystemSetting::get('appearance.font_heading', ''));
    }

    /**
     * <link> tags loading the selected Google families. Custom fonts need no
     * link — their @font-face rules are emitted by css().
     */
    public static function fontLinks(): string
    {
        $customNames = array_column(self::customFonts(), 'name');
        $families = [];
        foreach ([self::bodyFont(), self::headingFont()] as $family) {
            if ($family !== '' && ! in_array($family, $customNames, true)
                && in_array($family, self::googleFamilies(), true)
                && ! in_array($family, $families, true)) {
                $families[] = $family;
            }
        }
        if (! $families) {
            return '';
        }

        // v1 CSS API: serves the weights a family actually has (v2 rejects the
        // whole request when a listed weight is missing).
        $query = implode('|', array_map(
            fn ($f) => str_replace(' ', '+', $f).':300,400,500,600,700',
            $families
        ));

        return '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            .'<link href="https://fonts.googleapis.com/css?family='.e($query).'&display=swap" rel="stylesheet">';
    }

    /**
     * The appearance <style> block. Font rules apply everywhere; the
     * text-color overrides are only for visitor-facing layouts.
     */
    public static function css(bool $includeTextColors = false): string
    {
        $out = [];

        foreach (self::customFonts() as $font) {
            $url = StorageHelper::publicUrl($font['path']);
            if (! $url) {
                continue;
            }
            $format = match ($font['format'] ?? '') {
                'woff2' => 'woff2',
                'woff' => 'woff',
                'otf' => 'opentype',
                default => 'truetype',
            };
            // One file serves the whole weight range; browsers synthesize
            // bold/light when the file itself is single-weight.
            $out[] = "@font-face { font-family: '".addslashes($font['name'])."'; src: url('{$url}') format('{$format}'); font-weight: 100 900; font-display: swap; }";
        }

        $body = self::bodyFont();
        if ($body !== '') {
            // --font-outfit is the app-wide Tailwind font token (body carries
            // `font-outfit`), so overriding the variable rethemes everything.
            $out[] = ":root { --font-outfit: '{$body}', 'Outfit', sans-serif; }";
            $out[] = "body { font-family: '{$body}', 'Outfit', sans-serif; }";
        }

        $heading = self::headingFont();
        if ($heading !== '') {
            $out[] = "h1, h2, h3, h4, h5, h6 { font-family: '{$heading}', 'Outfit', sans-serif; }";
        }

        if ($includeTextColors) {
            $out[] = self::textColorCss();
        }

        return implode("\n", array_filter($out));
    }

    /**
     * Visitor-page text colors. Each rule is emitted only when the admin set a
     * color, and overrides the text utilities actually used for that role —
     * unset colors leave the theme byte-for-byte untouched.
     */
    private static function textColorCss(): string
    {
        // Light selectors are scoped to html:not(.dark): an unscoped
        // `.text-gray-700 { … !important }` would also beat the element's own
        // `dark:` variant in dark mode.
        $roles = [
            // role => [light selectors, dark selectors]
            'heading' => [
                'html:not(.dark) h1, html:not(.dark) h2, html:not(.dark) h3, html:not(.dark) h4, html:not(.dark) h5, html:not(.dark) h6, html:not(.dark) .text-gray-800, html:not(.dark) .text-gray-900',
                'html.dark h1, html.dark h2, html.dark h3, html.dark h4, html.dark h5, html.dark h6, html.dark [class*="dark:text-white"], html.dark [class*="dark:text-gray-100"]',
            ],
            'body' => [
                'html:not(.dark) body, html:not(.dark) .text-gray-600, html:not(.dark) .text-gray-700',
                'html.dark body, html.dark [class*="dark:text-gray-200"], html.dark [class*="dark:text-gray-300"]',
            ],
            'muted' => [
                'html:not(.dark) .text-gray-400, html:not(.dark) .text-gray-500',
                'html.dark [class*="dark:text-gray-400"], html.dark [class*="dark:text-gray-500"]',
            ],
        ];

        $out = [];
        foreach ($roles as $role => [$lightSelector, $darkSelector]) {
            $light = BrandingHelper::sanitizeHex(SystemSetting::get("appearance.text_{$role}_light"), '');
            $dark = BrandingHelper::sanitizeHex(SystemSetting::get("appearance.text_{$role}_dark"), '');
            if ($light !== '') {
                $out[] = "{$lightSelector} { color: {$light} !important; }";
            }
            if ($dark !== '') {
                $out[] = "{$darkSelector} { color: {$dark} !important; }";
            }
        }

        return implode("\n", $out);
    }
}
