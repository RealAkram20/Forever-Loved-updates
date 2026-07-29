<?php

namespace App\Helpers;

use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\SystemSetting;

/**
 * Resolves an appearance setting for whoever is being served: the active reseller's own
 * value if they have set one, otherwise the platform's.
 *
 * This exists so BrandingHelper and AppearanceHelper need no per-key tenant logic. They
 * previously read SystemSetting directly and carried a hand-written override for exactly
 * three things (logo, favicon, primary colour) — so a reseller's white-labeled memorial page
 * rendered their logo and brand hue on top of the platform's buttons, page background,
 * fonts, CTA banner and dark theme. Routing those helpers' reads through here makes every
 * key they already understood per-reseller at once.
 */
class ThemeSetting
{
    /**
     * Columns that predate the reseller_settings table and are still the source of truth for
     * their key — logos and the favicon are uploaded files, and primary_color has been a
     * column since the reseller program shipped. Kept rather than migrated so existing rows,
     * the upload handlers and the admin views all keep working unchanged.
     */
    private const COLUMN_ALIASES = [
        'branding.logo_path' => 'logo_path',
        'branding.logo_dark_path' => 'logo_dark_path',
        'branding.favicon_path' => 'favicon_path',
        'branding.primary_color' => 'primary_color',
    ];

    /**
     * setting key => resellers column, for callers that must read or write the column rather
     * than a reseller_settings row.
     *
     * Exposed because the Appearance form has to agree with this map. It previously did not:
     * the form read only reseller_settings, so a reseller whose primary_color lived in the
     * column saw the *platform's* colour in the picker while their own rendered on the site —
     * and saving could not fix it, since the posted value matched the platform and was
     * discarded as "not an override" while the column kept winning.
     *
     * @return array<string, string>
     */
    public static function columnAliases(): array
    {
        return self::COLUMN_ALIASES;
    }

    /**
     * The reseller whose branding applies to this request, if any.
     *
     * Bound into the container by ResolveReseller (public subdomain),
     * ResolveResellerByCustomDomain (their own domain) and EnsureResellerActive (their staff
     * area). Falls back to the logged-in user's own reseller so a reseller's staff *and*
     * their clients see the same branding on every page they can reach — dashboard, memorial
     * editing, notifications — not only the routes that bind it explicitly.
     */
    public static function tenant(): ?Reseller
    {
        if (app()->bound(Reseller::class)) {
            return app(Reseller::class);
        }

        return auth()->user()?->reseller;
    }

    /**
     * Marker for "the tenant came from the URL", set by the middleware that resolve a reseller
     * from the request — their host, their custom domain, or the /r/{slug} fallback.
     */
    public const REQUEST_TENANT_FLAG = 'reseller.resolved_from_request';

    public static function markResolvedFromRequest(): void
    {
        app()->instance(self::REQUEST_TENANT_FLAG, true);
    }

    /**
     * Whether this request is being served as a reseller's *own public site*, as opposed to a
     * reseller-affiliated user browsing the platform's site.
     *
     * Both cases have a tenant, and both should wear the reseller's branding — that is
     * deliberate and predates this. But only the first should drop the platform's marketing
     * navigation: on their own domain, links to our About, Pricing and Contact walk their
     * visitor onto our site. On the platform's own host those links are simply correct.
     */
    public static function isResellerSite(): bool
    {
        return app()->bound(self::REQUEST_TENANT_FLAG) && self::tenant() !== null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $reseller = self::tenant();

        if ($reseller) {
            $override = self::tenantValue($reseller, $key);

            if ($override !== null) {
                return $override;
            }
        }

        return SystemSetting::get($key, $default);
    }

    /**
     * This reseller's value for the key, or null to mean "inherit the platform's".
     *
     * Absence and emptiness are deliberately different for stored settings: an empty value
     * is a real choice ("use the theme's default font, even though the platform picked one"),
     * so a present-but-empty row is returned rather than falling through. Column aliases have
     * no way to express that distinction, so blank there still means unset — which is how
     * they already behaved.
     */
    private static function tenantValue(Reseller $reseller, string $key): mixed
    {
        if (isset(self::COLUMN_ALIASES[$key])) {
            $value = $reseller->{self::COLUMN_ALIASES[$key]};

            return filled($value) ? $value : null;
        }

        $overrides = ResellerSetting::allFor($reseller->id);

        if (! array_key_exists($key, $overrides)) {
            return null;
        }

        return SystemSetting::castValue(
            (string) ($overrides[$key]['value'] ?? ''),
            $overrides[$key]['type'] ?? 'string'
        );
    }

    /** Whether the active tenant has overridden this key at all. Used to label the form. */
    public static function isOverridden(string $key): bool
    {
        $reseller = self::tenant();

        if (! $reseller) {
            return false;
        }

        if (isset(self::COLUMN_ALIASES[$key])) {
            return filled($reseller->{self::COLUMN_ALIASES[$key]});
        }

        return ResellerSetting::has($reseller->id, $key);
    }
}
