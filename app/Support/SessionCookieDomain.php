<?php

namespace App\Support;

/**
 * Which hosts the session cookie is sent back to.
 *
 * Laravel's default is null, which makes the cookie host-only: returned to exactly the host
 * that set it and to no subdomain of it. For an application whose tenants are all subdomains
 * of APP_URL, that default is wrong by construction — and it fails quietly.
 *
 * The report was "the super admin cannot edit the memorial on the live site, yet can reach the
 * admin areas". Both were true and both followed from this: staff sign in on the apex, so the
 * admin screens worked, but on a reseller's subdomain the browser sent no session cookie at
 * all. The memorial page still rendered, because it is public, and canBeEditedBy(null) is
 * false — so every editing control was simply absent. From the outside that reads as a
 * permissions bug.
 *
 * Derived rather than left to an environment variable, because the variable was missing on the
 * one environment that needed it and nothing anywhere said so. A class rather than a closure
 * inside config/session.php so the rule can be tested without a test having to mutate the
 * process environment out from under every test that runs after it.
 *
 * @see \App\Support\ResellerDomain for the other half — the base domain subdomains are minted under.
 */
class SessionCookieDomain
{
    /**
     * The cookie domain for a given APP_URL, or null where a dotted domain cannot be used.
     *
     * The leading dot is the whole point: it is what lets the cookie stored on the apex travel
     * to every subdomain beneath it. Custom domains are a separate origin and no cookie we set
     * can reach them; that handoff is what the signed relative URLs exist for.
     */
    public static function forAppUrl(?string $appUrl): ?string
    {
        $host = parse_url((string) $appUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        // A single-label host takes no dotted cookie domain, and browsers differ on whether
        // they will store one for `.localhost` at all — which would sign development out of
        // its own session rather than extend it.
        if (! str_contains($host, '.')) {
            return null;
        }

        // Cookie domains cannot be IP addresses. A staging box reached by address would be
        // signed out on every request.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        return '.'.ltrim($host, '.');
    }
}
