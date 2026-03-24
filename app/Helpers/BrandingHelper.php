<?php

namespace App\Helpers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

class BrandingHelper
{
    private static function publicDiskPathUrl(?string $path, string $fallbackAsset): string
    {
        if (empty($path)) {
            return asset($fallbackAsset);
        }

        if (! Storage::disk('public')->exists($path)) {
            return asset($fallbackAsset);
        }

        return StorageHelper::publicUrl($path) ?? asset($fallbackAsset);
    }

    /**
     * Get the logo URL for use in layouts. Returns custom logo if set, else default.
     */
    public static function logoUrl(?string $variant = 'light'): string
    {
        return self::publicDiskPathUrl(
            SystemSetting::get('branding.logo_path'),
            'images/logo/logo.svg'
        );
    }

    /**
     * Get the logo URL for dark mode. Uses logo_dark_path if set, else same as light.
     */
    public static function logoDarkUrl(): string
    {
        return self::publicDiskPathUrl(
            SystemSetting::get('branding.logo_dark_path'),
            'images/logo/logo-dark.svg'
        );
    }

    /**
     * Get the icon/small logo URL (collapsed sidebar).
     */
    public static function logoIconUrl(): string
    {
        return self::publicDiskPathUrl(
            SystemSetting::get('branding.logo_path'),
            'images/logo/logo-icon.svg'
        );
    }

    /**
     * Get the auth page logo URL.
     */
    public static function authLogoUrl(): string
    {
        return self::publicDiskPathUrl(
            SystemSetting::get('branding.logo_path'),
            'images/logo/auth-logo.svg'
        );
    }

    /**
     * Get the favicon URL.
     */
    public static function faviconUrl(): string
    {
        $favicon = SystemSetting::get('branding.favicon_path');
        if (! empty($favicon) && Storage::disk('public')->exists($favicon)) {
            return StorageHelper::publicUrl($favicon) ?? asset('favicon.ico');
        }

        return self::publicDiskPathUrl(
            SystemSetting::get('branding.logo_path'),
            'favicon.ico'
        );
    }

    /**
     * Get primary brand color from settings.
     */
    public static function primaryColor(): string
    {
        return SystemSetting::get('branding.primary_color', '#465fff');
    }

    /**
     * Get secondary brand color.
     */
    public static function secondaryColor(): string
    {
        return SystemSetting::get('branding.secondary_color', '#1e3a5f');
    }

    /**
     * Get accent brand color.
     */
    public static function accentColor(): string
    {
        return SystemSetting::get('branding.accent_color', '#f59e0b');
    }

    /**
     * Generate darker shade of hex color (simple - reduce brightness).
     */
    public static function darken(string $hex, int $percent = 10): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return $hex;
        }

        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) * (1 - $percent / 100)));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) * (1 - $percent / 100)));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) * (1 - $percent / 100)));

        return sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
    }

    /**
     * Generate lighter shade of hex color.
     */
    public static function lighten(string $hex, int $percent = 10): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return $hex;
        }

        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + (255 - hexdec(substr($hex, 0, 2))) * $percent / 100));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + (255 - hexdec(substr($hex, 2, 2))) * $percent / 100));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + (255 - hexdec(substr($hex, 4, 2))) * $percent / 100));

        return sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
    }

    /**
     * Get CSS variables for brand colors (for inline style block).
     */
    public static function brandColorCss(): string
    {
        $primary = self::primaryColor();
        $secondary = self::secondaryColor();
        $accent = self::accentColor();

        $brand500 = $primary;
        $brand600 = self::darken($primary, 8);
        $brand700 = self::darken($primary, 16);
        $brand400 = self::lighten($primary, 15);
        $brand300 = self::lighten($primary, 30);
        $brand200 = self::lighten($primary, 45);
        $brand100 = self::lighten($primary, 55);
        $brand50 = self::lighten($primary, 65);
        $brand25 = self::lighten($primary, 72);
        $brand800 = self::darken($primary, 24);
        $brand900 = self::darken($primary, 32);
        $brand950 = self::darken($primary, 40);

        $darkBrand500 = $secondary;
        $darkBrand600 = self::darken($secondary, 8);
        $darkBrand700 = self::darken($secondary, 16);
        $darkBrand400 = self::lighten($secondary, 15);
        $darkBrand300 = self::lighten($secondary, 30);
        $darkBrand200 = self::lighten($secondary, 45);
        $darkBrand100 = self::lighten($secondary, 55);
        $darkBrand50 = self::lighten($secondary, 65);
        $darkBrand25 = self::lighten($secondary, 72);
        $darkBrand800 = self::darken($secondary, 24);
        $darkBrand900 = self::darken($secondary, 32);
        $darkBrand950 = self::darken($secondary, 40);

        $accent500 = $accent;
        $accent600 = self::darken($accent, 8);
        $accent400 = self::lighten($accent, 15);
        $accent100 = self::lighten($accent, 55);
        $accent50 = self::lighten($accent, 65);

        return ":root {
  --color-brand-500: {$brand500};
  --color-brand-600: {$brand600};
  --color-brand-700: {$brand700};
  --color-brand-400: {$brand400};
  --color-brand-300: {$brand300};
  --color-brand-200: {$brand200};
  --color-brand-100: {$brand100};
  --color-brand-50: {$brand50};
  --color-brand-25: {$brand25};
  --color-brand-800: {$brand800};
  --color-brand-900: {$brand900};
  --color-brand-950: {$brand950};
  --color-accent-500: {$accent500};
  --color-accent-600: {$accent600};
  --color-accent-400: {$accent400};
  --color-accent-100: {$accent100};
  --color-accent-50: {$accent50};
}
html.dark {
  --color-brand-500: {$darkBrand500};
  --color-brand-600: {$darkBrand600};
  --color-brand-700: {$darkBrand700};
  --color-brand-400: {$darkBrand400};
  --color-brand-300: {$darkBrand300};
  --color-brand-200: {$darkBrand200};
  --color-brand-100: {$darkBrand100};
  --color-brand-50: {$darkBrand50};
  --color-brand-25: {$darkBrand25};
  --color-brand-800: {$darkBrand800};
  --color-brand-900: {$darkBrand900};
  --color-brand-950: {$darkBrand950};
}";
    }
}
