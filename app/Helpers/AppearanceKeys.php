<?php

namespace App\Helpers;

/**
 * The appearance settings vocabulary, in one place.
 *
 * Admin\AppearanceController and Reseller\AppearanceController validate and save the same
 * keys against the same rules — the only difference is where the values land (SystemSetting
 * vs ResellerSetting). These lists were previously private constants on the admin controller,
 * which would have meant maintaining a second copy for resellers and letting the two drift:
 * a colour added to one form would silently be unvalidated in the other, and every one of
 * these is interpolated into a <style> block.
 */
class AppearanceKeys
{
    /** Visitor-page text colours (heading / body / muted, light and dark). */
    public const TEXT_COLOR_KEYS = [
        'appearance.text_heading_light', 'appearance.text_heading_dark',
        'appearance.text_body_light', 'appearance.text_body_dark',
        'appearance.text_muted_light', 'appearance.text_muted_dark',
    ];

    /** Branding keys holding a colour. Validated as literal hex, never free-form strings. */
    public const BRANDING_COLOR_KEYS = [
        'branding.primary_color', 'branding.secondary_color', 'branding.accent_color',
        'branding.bg_light', 'branding.bg_dark',
        'branding.primary_light', 'branding.primary_dark',
        'branding.accent_light', 'branding.accent_dark',
        'branding.button1_color', 'branding.button1_text_color',
        'branding.button1_color_dark', 'branding.button1_text_color_dark',
        'branding.button2_color', 'branding.button2_text_color',
        'branding.button2_color_dark', 'branding.button2_text_color_dark',
        'branding.cta_bg_light', 'branding.cta_bg_dark',
        'branding.cta_btn1_color', 'branding.cta_btn1_text_color',
        'branding.cta_btn1_color_dark', 'branding.cta_btn1_text_color_dark',
        'branding.cta_btn2_color', 'branding.cta_btn2_text_color',
        'branding.cta_btn2_color_dark', 'branding.cta_btn2_text_color_dark',
    ];

    /** Branding keys holding a 0-100 percentage rather than a colour. */
    public const BRANDING_PERCENT_KEYS = [
        'branding.cta_overlay_light', 'branding.cta_overlay_dark',
    ];

    /**
     * The three colours the platform treats as required, so its palette can never be left
     * with no brand colour at all. A reseller may leave any of them unset — that inherits
     * the platform's rather than producing a blank, which is why the reseller form makes
     * none of these required.
     */
    public const REQUIRED_PLATFORM_COLOR_KEYS = [
        'branding.primary_color', 'branding.secondary_color', 'branding.accent_color',
    ];

    /** Every font-family key: the two site-wide ones plus one per typography role. */
    public static function fontKeys(): array
    {
        $keys = ['appearance.font_body', 'appearance.font_heading'];

        foreach (array_keys(AppearanceHelper::TYPE_ROLES) as $role) {
            $keys[] = "appearance.role_{$role}_font";
        }

        return $keys;
    }

    /** Per-role type colours, light and dark. */
    public static function roleColorKeys(): array
    {
        $keys = [];

        foreach (array_keys(AppearanceHelper::TYPE_ROLES) as $role) {
            $keys[] = "appearance.role_{$role}_color_light";
            $keys[] = "appearance.role_{$role}_color_dark";
        }

        return $keys;
    }

    /** Every colour key across both groups — the full set that must validate as hex. */
    public static function allColorKeys(): array
    {
        return array_merge(
            self::BRANDING_COLOR_KEYS,
            self::TEXT_COLOR_KEYS,
            self::roleColorKeys(),
        );
    }

    /**
     * Everything a reseller is allowed to set, used to reject anything else outright rather
     * than trusting the form to only contain safe names. Notably excludes
     * appearance.custom_fonts (uploads are platform-wide) and every non-appearance group —
     * payments, SMTP, AI credentials all live in the same key namespace.
     */
    public static function resellerWritable(): array
    {
        return array_merge(
            self::BRANDING_COLOR_KEYS,
            self::BRANDING_PERCENT_KEYS,
            self::TEXT_COLOR_KEYS,
            self::roleColorKeys(),
            self::fontKeys(),
            ['branding.default_theme'],
        );
    }
}
