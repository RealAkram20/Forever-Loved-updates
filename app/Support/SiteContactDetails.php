<?php

namespace App\Support;

use App\Helpers\ThemeSetting;

/**
 * The details a visitor needs to actually reach a business: address, phones, hours, socials,
 * and where it is on a map.
 *
 * These are not appearance settings — they are facts about the tenant — so they live outside
 * AppearanceKeys and are written from the reseller's Settings page rather than their theme.
 * They resolve through ThemeSetting all the same, so the platform can set defaults and a
 * reseller overrides them, and so a template can render them without knowing which layer
 * answered.
 *
 * Every one is optional. A funeral home that has not given us its opening hours renders a
 * shorter list, never a placeholder: a plausible-looking wrong address on this page sends
 * someone to the wrong building on the worst day of their year.
 */
class SiteContactDetails
{
    public const PHONE = 'branding.contact_phone';

    public const PHONE_ALT = 'branding.contact_phone_alt';

    public const ADDRESS = 'branding.contact_address';

    public const HOURS = 'branding.opening_hours';

    public const MAP_EMBED = 'branding.map_embed_url';

    public const SOCIAL_FACEBOOK = 'branding.social_facebook';

    public const SOCIAL_TWITTER = 'branding.social_twitter';

    public const SOCIAL_INSTAGRAM = 'branding.social_instagram';

    /**
     * Hosts whose embeds may be put in an iframe on a tenant's page.
     *
     * An allow-list, not a URL validator. This value is pasted in by a reseller and rendered
     * inside an iframe on their public site; without a host check it is an invitation to frame
     * anything at all — a phishing form wearing their branding, an ad network, a page that
     * reads the referrer of every grieving visitor. The list is short on purpose and adding to
     * it should be a deliberate decision, not a support fix.
     *
     * @var array<string, string> host => required path prefix
     */
    private const MAP_HOSTS = [
        'www.google.com' => '/maps/embed',
        'maps.google.com' => '/maps/embed',
        'www.openstreetmap.org' => '/export/embed.html',
        'openstreetmap.org' => '/export/embed.html',
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return [
            self::PHONE, self::PHONE_ALT, self::ADDRESS, self::HOURS, self::MAP_EMBED,
            self::SOCIAL_FACEBOOK, self::SOCIAL_TWITTER, self::SOCIAL_INSTAGRAM,
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        $url = ['nullable', 'string', 'url', 'max:500'];

        return [
            self::PHONE => ['nullable', 'string', 'max:40'],
            self::PHONE_ALT => ['nullable', 'string', 'max:40'],
            // Multi-line: an address is written the way it is written on an envelope.
            self::ADDRESS => ['nullable', 'string', 'max:300'],
            self::HOURS => ['nullable', 'string', 'max:300'],
            self::MAP_EMBED => ['nullable', 'string', 'max:1000'],
            self::SOCIAL_FACEBOOK => $url,
            self::SOCIAL_TWITTER => $url,
            self::SOCIAL_INSTAGRAM => $url,
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return ThemeSetting::get($key, $default);
    }

    /**
     * The map iframe URL for this site, or null when there is nothing safe to show.
     *
     * Accepts either the bare src a mapping site gives you or the whole `<iframe …>` snippet,
     * because "Embed a map" hands you the snippet and asking someone to extract the src from
     * it is how a settings field goes unused.
     */
    public static function mapEmbedUrl(): ?string
    {
        $value = trim((string) self::get(self::MAP_EMBED, ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
            $value = html_entity_decode($m[1], ENT_QUOTES);
        }

        return self::isAllowedMapUrl($value) ? $value : null;
    }

    public static function isAllowedMapUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if (! isset(self::MAP_HOSTS[$host])) {
            return false;
        }

        return str_starts_with($path, self::MAP_HOSTS[$host]);
    }

    /** Human-readable list of what may be pasted, for the settings hint and the error. */
    public static function allowedMapHostsLabel(): string
    {
        return 'Google Maps or OpenStreetMap';
    }

    /**
     * A link out to a full map, for the "open in maps" affordance beside an address. Built
     * from the address rather than the embed so it works even when no embed is configured.
     */
    public static function mapLinkUrl(): ?string
    {
        $address = trim((string) self::get(self::ADDRESS, ''));

        if ($address === '') {
            return null;
        }

        return 'https://www.openstreetmap.org/search?query='.urlencode(preg_replace('/\s+/', ' ', $address));
    }

    /** @return array<int, string> */
    public static function lines(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();
    }
}
