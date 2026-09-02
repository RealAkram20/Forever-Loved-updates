<?php

namespace App\Support;

/**
 * Which proxies the app believes about `X-Forwarded-*`.
 *
 * This used to be `trustProxies(at: '*')`, for a good reason that is written down in
 * `bootstrap/app.php`: customers deploy behind TLS-terminating proxies, and without trusting
 * them every HTTPS request looks like HTTP — secure session cookies never come back, OAuth
 * state checks fail, and generated URLs carry the wrong scheme. None of that changes here.
 *
 * **What `'*'` also did, unintentionally.** Trusting every proxy means trusting whatever the
 * *client* put in `X-Forwarded-For`. Symfony walks that chain from the right and stops at the
 * first hop it does not trust; when everything is trusted it walks all the way to the leftmost
 * entry, which is the one the caller supplied. `Request::ip()` then returns an attacker-chosen
 * string — and `Request::ip()` is exactly what Laravel's `throttle` middleware keys on. Every
 * one of the twenty-two rate limits on this app's public routes could be reset per request by
 * varying one header.
 *
 * Trusting the private ranges instead fixes it without losing the scheme detection: the real
 * edge (Traefik/nginx on the container network) is still trusted, so `X-Forwarded-Proto` is
 * still believed, but a public client's forged left-hand entry no longer wins.
 *
 * **If this breaks something, it breaks loudly and it is one env var to undo.** Set
 * `TRUSTED_PROXIES=*` to restore the previous behaviour exactly. Symptoms to watch for after
 * deploying this: redirect loops, "CSRF token mismatch" on every form, or being logged out on
 * each request — all of which mean the app has stopped seeing requests as HTTPS because the
 * real proxy is not inside the ranges below.
 */
class TrustedProxies
{
    /**
     * Loopback plus the RFC1918 / RFC4193 private ranges.
     *
     * A container-networked reverse proxy — which is what Coolify's Traefik is, and what the
     * app's own nginx is — always reaches PHP from one of these. A proxy on a public address
     * would need adding explicitly via the env var.
     */
    private const PRIVATE_RANGES = [
        '127.0.0.1/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '::1/128',
        'fc00::/7',
    ];

    /**
     * @return string|array<int, string>
     */
    public static function list(): string|array
    {
        $configured = trim((string) env('TRUSTED_PROXIES', ''));

        if ($configured === '') {
            return self::PRIVATE_RANGES;
        }

        // The escape hatch, kept verbatim rather than folded into the list: '*' has a distinct
        // meaning to Symfony and is not a CIDR.
        if ($configured === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
