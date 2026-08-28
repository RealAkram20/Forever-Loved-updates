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
     * Marker for "this is one of the platform's own public pages, so the platform is the
     * brand" — set by UsePlatformBranding on our marketing routes. See tenant().
     */
    public const PLATFORM_BRANDING_FLAG = 'branding.platform_only';

    public static function usePlatformBranding(): void
    {
        app()->instance(self::PLATFORM_BRANDING_FLAG, true);
    }

    /**
     * The reseller whose branding applies to this request, if any.
     *
     * Bound into the container by ResolveReseller (public subdomain),
     * ResolveResellerByCustomDomain (their own domain) and EnsureResellerActive (their staff
     * area). Falls back to the logged-in user's own reseller so a reseller's staff *and*
     * their clients see the same branding on every page they can reach — dashboard, memorial
     * editing, notifications — not only the routes that bind it explicitly.
     *
     * That fallback is switched off on our own marketing pages. A reseller's staff are also
     * the people we are selling to, and our home page arriving in *their* logo and colours —
     * our copy, our prices, their brand — misleads the reader about whose offer they are
     * reading. Our shop window is ours. Only the fallback is suppressed: a reseller bound
     * from the request still wins, so nothing changes anywhere on a reseller's own site.
     */
    public static function tenant(): ?Reseller
    {
        if (app()->bound(Reseller::class)) {
            return app(Reseller::class);
        }

        if (app()->bound(self::PLATFORM_BRANDING_FLAG)) {
            return null;
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
     * The tenant whose *content* this request should serve, or null for the platform's.
     *
     * Deliberately stricter than tenant(). That one falls back to the signed-in user's own
     * reseller so branding follows a reseller's staff and clients everywhere they go, which
     * is right for colours and logos. It is wrong for pages: it would serve a reseller's own
     * About and Pricing on the *platform's* domain to anyone affiliated with them, and leave
     * them no way to read ours.
     *
     * Content follows the host. Whose site is this, not who is looking at it.
     */
    public static function siteTenant(): ?Reseller
    {
        return self::isResellerSite() ? self::tenant() : null;
    }

    public static function siteTenantId(): ?int
    {
        return self::siteTenant()?->id;
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

    /**
     * Three layers, narrowest first:
     *
     *   1. what this reseller explicitly set   (reseller_settings / the column aliases)
     *   2. what their chosen theme sets        (Theme::tokenValues())
     *   3. the platform's default              (SystemSetting)
     *
     * A theme therefore ships a coherent palette while a reseller keeps the last word on any
     * single value of it — which is the relationship the Appearance page already implies, and
     * the reason applying a theme never silently discards colours somebody tuned by hand. The
     * cost is one confusing state, so the theme page names it rather than leaving it to be
     * discovered: "N of your own colours are overriding this theme".
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $reseller = self::tenant();

        if ($reseller) {
            $override = self::tenantValue($reseller, $key);

            if ($override !== null) {
                return $override;
            }

            $themed = self::themeValue($reseller, $key);

            if ($themed !== null) {
                return $themed;
            }
        }

        return SystemSetting::get($key, $default);
    }

    /** @var array<int, array<string, string>> reseller id => token map, for this request */
    private static array $themeTokens = [];

    /**
     * This reseller's theme tokens, loaded once per request.
     *
     * Memoised because brandColorCss() alone reads about forty keys to build a single
     * `<style>` block; a query per key would put the theme layer on the critical path of
     * every page load.
     *
     * Keyed off tenant() rather than siteTenant() on purpose: colours follow the brand, so a
     * reseller's staff see their own palette on our host too. Markup follows the host and is
     * resolved separately — see App\Themes\ActiveTheme.
     */
    private static function themeValue(Reseller $reseller, string $key): mixed
    {
        if (! array_key_exists($reseller->id, self::$themeTokens)) {
            self::$themeTokens[$reseller->id] = $reseller->theme?->tokenValues() ?? [];
        }

        return self::$themeTokens[$reseller->id][$key] ?? null;
    }

    /**
     * Serve this request a palette other than the one the reseller has applied.
     *
     * The single seam theme preview needs. It primes the same per-request memo themeValue()
     * would otherwise fill from the database, so every reader downstream — brandColorCss(),
     * the font links, a widget asking for one key — sees the previewed theme without knowing
     * a preview exists.
     *
     * Deliberately only the *theme* layer. Layer 1, whatever the reseller set by hand, still
     * wins over it, exactly as it would if the theme were applied for real. A preview that
     * skipped that would show them a site they cannot actually have.
     *
     * Per-request and never written down. Nothing here touches resellers.theme_id.
     *
     * @param  array<string, string>  $tokens
     */
    public static function useThemeTokens(int $resellerId, array $tokens): void
    {
        self::$themeTokens[$resellerId] = $tokens;
    }

    /** Drop the memoised tokens — after a theme is applied, and between tests. */
    public static function forgetThemeTokens(?int $resellerId = null): void
    {
        if ($resellerId === null) {
            self::$themeTokens = [];

            return;
        }

        unset(self::$themeTokens[$resellerId]);
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

    /**
     * Only what this tenant has written themselves, with no fallback at all.
     *
     * get() layers tenant → theme → platform, which is right for a colour: something always
     * has to be painted. It is wrong for words. A reseller who has not written a footer line
     * should get an empty footer, not ours — "Celebrate lives that matter" is our marketing,
     * and under a funeral home's logo it describes the wrong company to a grieving family.
     *
     * Returns null on the platform's own site, where there is no tenant to have an opinion.
     */
    public static function tenantOwn(string $key): ?string
    {
        $reseller = self::siteTenant();

        if (! $reseller) {
            return null;
        }

        $value = ResellerSetting::allFor($reseller->id)[$key]['value'] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
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
