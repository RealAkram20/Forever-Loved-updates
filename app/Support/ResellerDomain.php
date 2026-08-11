<?php

namespace App\Support;

/**
 * Derives the reseller program's domain settings from the app's own URL.
 *
 * Exists so config/reseller.php has no logic worth testing hidden inside it, and so the
 * rules below can be asserted directly against a table of realistic URLs.
 *
 * The point is that nothing in this codebase names a domain we might not own. Every
 * install is correct for the host it is actually served from, and RESELLER_APP_DOMAIN
 * becomes an override for the genuinely different case rather than a required setting
 * that silently defaults to somebody else's domain.
 */
class ResellerDomain
{
    /**
     * The base domain reseller subdomains are minted under, taken from APP_URL.
     */
    public static function fromAppUrl(?string $appUrl): string
    {
        $host = strtolower((string) (parse_url((string) $appUrl, PHP_URL_HOST) ?: ''));

        if ($host === '') {
            // APP_URL missing or unparseable. 'localhost' is the honest answer — and it has
            // no dot, so Reseller::subdomainRoutingAvailable() will refuse to mint
            // subdomains against it rather than handing out dead addresses.
            return 'localhost';
        }

        // A port is not part of the domain, and 'www' is a host label rather than a base
        // domain — minting acme.www.example.com would be wrong.
        return (string) preg_replace(['/:\d+$/', '/^www\./'], '', $host);
    }

    /**
     * Every hostname that should be treated as the platform's own rather than a reseller's.
     *
     * A site is nearly always reachable at both example.com and www.example.com, but only
     * the exact APP_URL host was ever recognised — so on the other form the reseller
     * custom-domain routes claimed `/` and the home page 404'd. Both forms, port stripped,
     * lowercased, so callers can compare a raw Host header directly.
     *
     * @return list<string>
     */
    public static function platformHosts(?string $appHost): array
    {
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) $appHost));

        if ($host === '') {
            $host = 'localhost';
        }

        $sibling = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;

        return array_values(array_unique(array_filter([$host, $sibling])));
    }

    /**
     * Host prefix for the custom-domain ownership challenge: <prefix>.<their domain>.
     *
     * Namespaced by the app's own first label so the record is self-describing in a
     * registrar's control panel, and cannot collide with the TXT records of other services
     * the reseller's domain already uses.
     */
    public static function verificationPrefix(?string $appUrl): string
    {
        $base = self::fromAppUrl($appUrl);
        $label = (string) preg_replace('/[^a-z0-9]/', '', strtok($base, '.') ?: '');

        return '_'.($label !== '' ? $label : 'site').'-verify';
    }
}
