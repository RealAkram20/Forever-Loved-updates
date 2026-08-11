<?php

namespace App\Helpers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Brand assets and the CSS custom properties the whole UI is themed from.
 *
 * Every setting here is read through ThemeSetting, which resolves the active reseller's own
 * value before the platform's — so a reseller's memorial pages, dashboard and auth screens
 * carry their palette, not just their logo. Reading SystemSetting directly here would take
 * that away again, so don't.
 */
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
     * Get the logo URL for use in layouts. Returns the reseller's own logo when resolved,
     * else the platform's custom logo if set, else the default.
     */
    public static function logoUrl(?string $variant = 'light'): string
    {
        // $variant was previously accepted and then ignored, so every caller asking for the
        // dark mark silently got the light one.
        if ($variant === 'dark') {
            return self::logoDarkUrl();
        }

        return self::publicDiskPathUrl(
            ThemeSetting::get('branding.logo_path'),
            'images/logo/logo.svg'
        );
    }

    /**
     * Get the logo URL for dark mode.
     *
     * Not a plain ThemeSetting lookup, because the fallback chain differs by tenant: a
     * reseller who uploaded only one logo must still see *their* brand in dark mode, so
     * their light logo stands in. Falling through to the platform's dark mark — which is
     * what a single-key lookup would do — would put our logo on their white-labeled page.
     */
    public static function logoDarkUrl(): string
    {
        $reseller = ThemeSetting::tenant();

        $tenantLogo = $reseller
            ? (filled($reseller->logo_dark_path) ? $reseller->logo_dark_path : $reseller->logo_path)
            : null;

        return self::publicDiskPathUrl(
            filled($tenantLogo) ? $tenantLogo : SystemSetting::get('branding.logo_dark_path'),
            'images/logo/logo-dark.svg'
        );
    }

    /**
     * Get the icon/small logo URL (collapsed sidebar). The favicon is the square-format
     * asset actually meant for this size, so it wins if set — same priority order as
     * faviconUrl() — else the full logo gets used, else the platform's default icon.
     */
    public static function logoIconUrl(): string
    {
        $favicon = ThemeSetting::get('branding.favicon_path');
        if (! empty($favicon) && Storage::disk('public')->exists($favicon)) {
            return StorageHelper::publicUrl($favicon) ?? asset('images/logo/logo-icon.svg');
        }

        return self::publicDiskPathUrl(
            ThemeSetting::get('branding.logo_path'),
            'images/logo/logo-icon.svg'
        );
    }

    /**
     * Get the auth page logo URL.
     */
    public static function authLogoUrl(): string
    {
        return self::publicDiskPathUrl(
            ThemeSetting::get('branding.logo_path'),
            'images/logo/auth-logo.svg'
        );
    }

    /**
     * The name shown in the browser tab title.
     *
     * A reseller's own business name whenever one is active. The platform's name in the tab of
     * a white-labeled memorial page is visible to every family who opens it, and rides along in
     * every bookmark, shared screenshot and browser-history entry.
     *
     * Falls back to config('app.name') exactly as before, so platform page titles are unchanged.
     * Deliberately not branding.app_name: switching platform titles to a different source is a
     * separate decision from fixing the tenant leak.
     */
    public static function documentTitleName(): string
    {
        $tenantName = ThemeSetting::tenant()?->name;

        return filled($tenantName) ? (string) $tenantName : (string) config('app.name', 'Forever-Loved');
    }

    /**
     * The display name for anything addressed to a specific reseller's person — mail
     * subjects, from-names, sign-offs — resolvable from a queue worker, where no
     * request or session exists. Entity-keyed counterpart of documentTitleName():
     * the reseller's business name when there is one, else the admin-configured
     * platform name (branding.app_name), else APP_NAME. The one fallback chain,
     * because five hand-rolled copies had already drifted into three spellings.
     */
    public static function displayNameFor(?\App\Models\Reseller $reseller): string
    {
        return filled($reseller?->name)
            ? (string) $reseller->name
            : (string) \App\Models\SystemSetting::get('branding.app_name', config('app.name', 'Forever Loved'));
    }

    /**
     * The support address shown on the site being served — footer, contact page, the
     * contact-form widget. On a reseller's own site that must be *their* inbox: the
     * platform's support address on a white-labeled page hands their client
     * relationship to us, one mailto at a time. Falls back to the platform's sending
     * address exactly as those templates always did.
     */
    public static function contactEmail(): ?string
    {
        $tenantEmail = ThemeSetting::siteTenant()?->contact_email;

        return filled($tenantEmail)
            ? (string) $tenantEmail
            : \App\Models\SystemSetting::get('smtp.from_address');
    }

    /**
     * Default UI theme when the browser has no saved preference (localStorage `theme`).
     */
    public static function defaultTheme(): string
    {
        $v = ThemeSetting::get('branding.default_theme', 'light');

        return in_array($v, ['light', 'dark'], true) ? $v : 'light';
    }

    /**
     * Get the favicon URL. Reseller's own favicon wins if set, else their logo, else the
     * platform's favicon/logo, else the default.
     */
    public static function faviconUrl(): string
    {
        $favicon = ThemeSetting::get('branding.favicon_path');
        if (! empty($favicon) && Storage::disk('public')->exists($favicon)) {
            return StorageHelper::publicUrl($favicon) ?? asset('favicon.ico');
        }

        return self::publicDiskPathUrl(
            ThemeSetting::get('branding.logo_path'),
            'favicon.ico'
        );
    }

    /**
     * Get primary brand color from settings.
     */
    public static function primaryColor(): string
    {
        return ThemeSetting::get('branding.primary_color', '#465fff');
    }

    /**
     * Get secondary brand color.
     */
    public static function secondaryColor(): string
    {
        return ThemeSetting::get('branding.secondary_color', '#1e3a5f');
    }

    /**
     * Get accent brand color.
     */
    public static function accentColor(): string
    {
        return ThemeSetting::get('branding.accent_color', '#f59e0b');
    }

    public static function bgLight(): string
    {
        return ThemeSetting::get('branding.bg_light', '#f9fafb');
    }

    public static function bgDark(): string
    {
        return ThemeSetting::get('branding.bg_dark', '#101828');
    }

    public static function primaryLight(): string
    {
        return ThemeSetting::get('branding.primary_light', '#465fff');
    }

    public static function primaryDark(): string
    {
        return ThemeSetting::get('branding.primary_dark', '#1e3a5f');
    }

    public static function accentLight(): string
    {
        return ThemeSetting::get('branding.accent_light', '#f59e0b');
    }

    public static function accentDark(): string
    {
        return ThemeSetting::get('branding.accent_dark', '#f59e0b');
    }

    public static function button1Color(): string
    {
        return self::sanitizeHex(ThemeSetting::get('branding.button1_color'), '#465fff');
    }

    public static function button1TextColor(): string
    {
        return self::sanitizeHex(ThemeSetting::get('branding.button1_text_color'), '#ffffff');
    }

    /**
     * A dark-mode colour whose declared default is "same as light".
     *
     * The platform always has a stored value for every dark key, so a plain lookup would let
     * the platform's dark colour beat a reseller's own light choice — a reseller who set only
     * their primary button colour would get their green in light mode and our blue in dark.
     * When a tenant is active and has not set this dark key themselves, their light value
     * stands in, which is what the non-tenant default already expressed.
     */
    private static function tenantAwareDark(string $key, string $lightFallback): string
    {
        if (ThemeSetting::tenant() && ! ThemeSetting::isOverridden($key)) {
            return $lightFallback;
        }

        return self::sanitizeHex(ThemeSetting::get($key), $lightFallback);
    }

    public static function button1ColorDark(): string
    {
        return self::tenantAwareDark('branding.button1_color_dark', self::button1Color());
    }

    public static function button1TextColorDark(): string
    {
        return self::tenantAwareDark('branding.button1_text_color_dark', self::button1TextColor());
    }

    public static function button2Color(): string
    {
        return self::sanitizeHex(ThemeSetting::get('branding.button2_color'), '#ffffff');
    }

    public static function button2TextColor(): string
    {
        return self::sanitizeHex(ThemeSetting::get('branding.button2_text_color'), '#374151');
    }

    public static function button2ColorDark(): string
    {
        return self::sanitizeHex(ThemeSetting::get('branding.button2_color_dark'), '#1f2937');
    }

    public static function button2TextColorDark(): string
    {
        return self::sanitizeHex(ThemeSetting::get('branding.button2_text_color_dark'), '#d1d5db');
    }

    public static function ctaBgLight(): string
    {
        return self::sanitizeHex(ThemeSetting::get('branding.cta_bg_light'), '#465fff');
    }

    public static function ctaBgDark(): string
    {
        return self::sanitizeHex(ThemeSetting::get('branding.cta_bg_dark'), '#3641f5');
    }

    /**
     * Strength of the scrim the CTA banner lays over its artwork, 0-100. It exists so the
     * headline stays legible whatever image is behind it; 0 means no scrim at all.
     */
    public static function ctaOverlayLight(): int
    {
        return self::clampPercent(ThemeSetting::get('branding.cta_overlay_light'), 0);
    }

    public static function ctaOverlayDark(): int
    {
        return self::clampPercent(ThemeSetting::get('branding.cta_overlay_dark'), 55);
    }

    public static function clampPercent(mixed $value, int $fallback = 0): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max(0, min(100, (int) $value));
    }

    /**
     * Colors are interpolated straight into a <style> block, so anything that is not a
     * literal hex value must never reach the stylesheet.
     */
    public static function sanitizeHex(mixed $value, string $fallback = '#000000'): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $value = trim($value);
        if (! preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return $fallback;
        }

        $hex = ltrim($value, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.strtolower($hex);
    }

    /**
     * Hex to `rgba(r, g, b, a)` — used to derive each button's glow from its own background.
     */
    public static function rgba(string $hex, float $alpha): string
    {
        $hex = ltrim(self::sanitizeHex($hex), '#');

        return sprintf(
            'rgba(%d, %d, %d, %s)',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.')
        );
    }

    /**
     * Perceived brightness (0-255) — lets the glow back off on near-white buttons,
     * where a colored halo would just read as a dirty smudge.
     */
    public static function luminance(string $hex): float
    {
        $hex = ltrim(self::sanitizeHex($hex), '#');

        return 0.299 * hexdec(substr($hex, 0, 2))
            + 0.587 * hexdec(substr($hex, 2, 2))
            + 0.114 * hexdec(substr($hex, 4, 2));
    }

    /**
     * Generate darker shade of hex color (simple - reduce brightness).
     */
    public static function darken(string $hex, int $percent = 10): string
    {
        $hex = ltrim(self::sanitizeHex($hex), '#');

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
        $hex = ltrim(self::sanitizeHex($hex), '#');

        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + (255 - hexdec(substr($hex, 0, 2))) * $percent / 100));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + (255 - hexdec(substr($hex, 2, 2))) * $percent / 100));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + (255 - hexdec(substr($hex, 4, 2))) * $percent / 100));

        return sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
    }

    /**
     * The custom properties for ONE button role. The admin only ever picks a background and a
     * text color; the hover shade, border, glow and focus ring are all derived from those.
     *
     * @param  string  $role  the CSS variable infix, e.g. `primary` -> --color-btn-primary
     */
    private static function buttonVars(string $role, string $bg, string $text): string
    {
        $bgLum = self::luminance($bg);

        // Light buttons darken on hover, dark ones lighten — a fixed direction would make the
        // hover state invisible on one end or the other.
        $hover = $bgLum > 55 ? self::darken($bg, 8) : self::lighten($bg, 14);

        // The glow is normally the button's own color. On a near-white button that halo would
        // just read as a grey smudge, so borrow the text color instead.
        $glowSource = $bgLum > 225 ? $text : $bg;
        $alpha = self::luminance($glowSource) > 225 ? 0.14 : 0.35;

        $p = "--color-btn-{$role}";

        return implode("\n", [
            "  {$p}: {$bg};",
            "  {$p}-hover: ".$hover.';',
            "  {$p}-text: {$text};",
            "  {$p}-border: ".self::rgba($text, 0.25).';',
            "  {$p}-glow: ".self::rgba($glowSource, $alpha).';',
            "  {$p}-glow-strong: ".self::rgba($glowSource, min(1, $alpha + 0.15)).';',
            "  {$p}-ring: ".self::rgba($glowSource, 0.35).';',
        ]);
    }

    /**
     * All four button roles for one theme.
     *
     * @param  string  $suffix  '' for light, '_dark' for the dark-mode setting keys
     */
    private static function buttonCss(string $suffix): string
    {
        $roles = [
            'primary' => ['button1_color', 'button1_text_color', '#465fff', '#ffffff'],
            'secondary' => ['button2_color', 'button2_text_color', '#ffffff', '#374151'],
            'cta-primary' => ['cta_btn1_color', 'cta_btn1_text_color', '#ffffff', '#465fff'],
            'cta-secondary' => ['cta_btn2_color', 'cta_btn2_text_color', '#3641f5', '#ffffff'],
        ];

        $out = [];
        foreach ($roles as $role => [$bgKey, $textKey, $bgDefault, $textDefault]) {
            $bg = self::sanitizeHex(ThemeSetting::get("branding.{$bgKey}{$suffix}"), $bgDefault);
            $text = self::sanitizeHex(ThemeSetting::get("branding.{$textKey}{$suffix}"), $textDefault);
            $out[] = self::buttonVars($role, $bg, $text);
        }

        return implode("\n", $out);
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

        $bgLight = self::bgLight();
        $bgDark = self::bgDark();
        $accentLight = self::accentLight();
        $accentDark = self::accentDark();
        $ctaLight = self::ctaBgLight();
        $ctaDark = self::ctaBgDark();

        // The scrim is the banner's own color, faded — it darkens (or lightens) the artwork
        // under the copy without introducing a color that isn't already in the palette. The
        // `-soft` stop is the same color at roughly half strength, so the gradient has a
        // middle to travel through instead of falling straight to transparent.
        $scrimLight = self::rgba($ctaLight, self::ctaOverlayLight() / 100);
        $scrimLightSoft = self::rgba($ctaLight, self::ctaOverlayLight() / 100 * 0.55);
        $scrimDark = self::rgba($ctaDark, self::ctaOverlayDark() / 100);
        $scrimDarkSoft = self::rgba($ctaDark, self::ctaOverlayDark() / 100 * 0.55);

        $btn = self::buttonCss('');
        $btnDark = self::buttonCss('_dark');

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
  --color-bg-page: {$bgLight};
  --color-accent-light: {$accentLight};
  --color-accent-dark: {$accentDark};
{$btn}
  --color-cta-bg: {$ctaLight};
  --color-cta-bg-hover: ".self::darken($ctaLight, 8).";
  --color-cta-scrim: {$scrimLight};
  --color-cta-scrim-soft: {$scrimLightSoft};
}
html.dark {
{$btnDark}
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
  --color-bg-page: {$bgDark};
  --color-accent-500: ".self::lighten($accentDark, 0).';
  --color-accent-600: '.self::darken($accentDark, 8).';
  --color-accent-400: '.self::lighten($accentDark, 15).';
  --color-accent-100: '.self::lighten($accentDark, 55).';
  --color-accent-50: '.self::lighten($accentDark, 65).";
  --color-cta-bg: {$ctaDark};
  --color-cta-bg-hover: ".self::darken($ctaDark, 8).";
  --color-cta-scrim: {$scrimDark};
  --color-cta-scrim-soft: {$scrimDarkSoft};
}";
    }
}
