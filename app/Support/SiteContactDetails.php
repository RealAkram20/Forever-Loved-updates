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
    /**
     * The map to show beside the address.
     *
     * Prefers whatever the business pasted, and otherwise builds one from the address itself —
     * because asking a funeral director to find "Share → Embed a map", copy an iframe, and get
     * a `pb=` parameter four hundred characters long into a form intact is asking for the
     * failure we already had: a truncated one, rendering "Invalid 'pb' parameter" where their
     * location should be.
     *
     * Typing the address is the whole job now. The pasted field stays for anyone who wants a
     * particular view — a pinned suite number, a satellite layer — and still wins when set.
     */
    public static function mapEmbedUrl(): ?string
    {
        $value = trim((string) self::get(self::MAP_EMBED, ''));

        if ($value !== '') {
            if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
                $value = html_entity_decode($m[1], ENT_QUOTES);
            }

            if (self::isAllowedMapUrl($value)) {
                return $value;
            }
        }

        return self::mapEmbedUrlFromAddress();
    }

    /**
     * A map centred on the saved address, with no API key and no geocoding step.
     *
     * `output=embed` is the keyless form Google has served for years; the alternative needs a
     * Maps Platform key, which is a bill and a console for something a reseller should get by
     * typing where they are.
     *
     * Built here rather than stored, so it follows the address: change the address and the map
     * moves with it, instead of quietly pointing at the old premises.
     */
    public static function mapEmbedUrlFromAddress(): ?string
    {
        $address = trim((string) self::get(self::ADDRESS, ''));

        if ($address === '') {
            return null;
        }

        // The address is a textarea — newlines separate the lines of a postal address, and a
        // query wants them as one line.
        $query = trim(preg_replace('/\s+/', ' ', $address));

        // z=16 is street level: the road named in the address, its neighbours, and enough
        // around it to recognise. Without a zoom Google picks its own, and for an address it
        // cannot pin exactly that means the whole country — a marker somewhere in Uganda tells
        // a family nothing about where to bring flowers.
        return 'https://maps.google.com/maps?q='.urlencode($query).'&z=16&output=embed';
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
