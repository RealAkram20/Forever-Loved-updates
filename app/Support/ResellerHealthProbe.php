<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Read-only probes for the infrastructure the reseller program depends on but the
 * app cannot provision: wildcard DNS, the wildcard TLS certificate, and the HTTP
 * path to a host. The app mints {slug}.{base} addresses the moment a reseller is
 * created; these probes exist so an admin learns those addresses are dead from a
 * banner or `reseller:doctor` instead of from a grieving family.
 *
 * Injectable (bound as a plain class) so tests can swap in a fake instead of
 * doing real network I/O.
 */
class ResellerHealthProbe
{
    /**
     * Where the scheduled probe leaves its verdict for the admin roster to read.
     * Cached rather than probed inline: a DNS lookup on a page render blocks for the
     * resolver timeout under exactly the condition being detected.
     */
    public const WILDCARD_DEAD_CACHE_KEY = 'reseller.health.wildcard-dns-dead';

    /**
     * Probe the wildcard and record the verdict for the admin banner. Called from the
     * scheduler and from reseller:doctor, never from a web request.
     */
    public function refreshWildcardVerdict(): bool
    {
        $dead = $this->wildcardResolvedIp() === null;

        Cache::put(self::WILDCARD_DEAD_CACHE_KEY, $dead, now()->addDay());

        return $dead;
    }

    /**
     * Resolve a random label under the reseller base domain. Only a wildcard record
     * can answer for a label nobody ever created, so an IP here proves the wildcard
     * exists; null means every un-provisioned reseller subdomain is NXDOMAIN.
     */
    public function wildcardResolvedIp(): ?string
    {
        return $this->resolveIp('probe-'.Str::lower(Str::random(10)).'.'.config('reseller.domain'));
    }

    /** The apex's own address, for comparing against what the wildcard resolves to. */
    public function apexIp(): ?string
    {
        return $this->resolveIp((string) config('reseller.domain'));
    }

    /** First A record for a host, or null when it does not resolve. */
    public function resolveIp(string $host): ?string
    {
        $records = @dns_get_record($host, DNS_A);

        return $records ? ($records[0]['ip'] ?? null) : null;
    }

    /**
     * TLS handshake against $connectIp (or the host itself) presenting $host via SNI,
     * reporting whether the served certificate actually covers it.
     *
     * The failure mode this distinguishes: Traefik answering with its self-signed
     * "TRAEFIK DEFAULT CERT" because the certresolver named in the router labels is
     * not defined on the proxy — DNS fine, routing fine, certificate warning anyway.
     *
     * @return array{ok: bool, error: ?string, issuer: ?string, sans: list<string>, expires_at: ?string}
     */
    public function tlsReport(string $host, ?string $connectIp = null): array
    {
        $report = ['ok' => false, 'error' => null, 'issuer' => null, 'sans' => [], 'expires_at' => null];

        $context = stream_context_create(['ssl' => [
            'SNI_enabled' => true,
            'peer_name' => $host,
            'capture_peer_cert' => true,
            // Verification is done by hand below so an untrusted cert can still be
            // *described* — "self-signed TRAEFIK DEFAULT CERT" is the diagnosis,
            // not an error to hide.
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]]);

        $client = @stream_socket_client(
            'ssl://'.($connectIp ?: $host).':443',
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($client === false) {
            $report['error'] = $errstr ?: 'connection failed';

            return $report;
        }

        $cert = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $parsed = $cert ? openssl_x509_parse($cert) : false;

        if ($parsed === false) {
            $report['error'] = 'no certificate presented';

            return $report;
        }

        $issuer = $parsed['issuer'] ?? [];
        $report['issuer'] = trim(implode(' ', array_filter([$issuer['O'] ?? null, $issuer['CN'] ?? null]))) ?: null;
        $report['expires_at'] = isset($parsed['validTo_time_t']) ? date('Y-m-d', $parsed['validTo_time_t']) : null;

        foreach (explode(',', (string) ($parsed['extensions']['subjectAltName'] ?? '')) as $entry) {
            $entry = trim($entry);
            if (str_starts_with($entry, 'DNS:')) {
                $report['sans'][] = substr($entry, 4);
            }
        }
        if ($report['sans'] === [] && isset($parsed['subject']['CN'])) {
            $report['sans'][] = (string) $parsed['subject']['CN'];
        }

        $notExpired = ($parsed['validTo_time_t'] ?? 0) > time();
        $report['ok'] = $notExpired && $this->certCovers($host, $report['sans']);

        if (! $report['ok'] && $report['error'] === null) {
            $report['error'] = $notExpired
                ? sprintf('certificate covers [%s], not %s', implode(', ', $report['sans']), $host)
                : 'certificate expired '.$report['expires_at'];
        }

        return $report;
    }

    /** Whether an HTTPS GET to the URL answers with anything below 500. */
    public function httpOk(string $url): bool
    {
        try {
            return Http::timeout(5)->withoutVerifying()->get($url)->status() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Certificate name matching: exact, or single-label wildcard. */
    private function certCovers(string $host, array $sans): bool
    {
        $host = strtolower($host);

        foreach ($sans as $san) {
            $san = strtolower($san);

            if ($san === $host) {
                return true;
            }

            if (str_starts_with($san, '*.')) {
                $suffix = substr($san, 1); // ".example.com"
                if (str_ends_with($host, $suffix) && ! str_contains(substr($host, 0, -strlen($suffix)), '.')) {
                    return true;
                }
            }
        }

        return false;
    }
}
