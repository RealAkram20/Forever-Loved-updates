<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Proves a reseller controls the DNS for a custom domain before we route traffic to
 * their tenant for it, via a TXT record challenge (like most SaaS custom-domain flows).
 * Deliberately says nothing about where their CNAME should point or about SSL — those
 * depend on hosting decisions that haven't been made yet; this only answers "do they
 * control this domain's DNS," which is true regardless of where the app ends up hosted.
 */
class DomainVerificationService
{
    public function generateToken(): string
    {
        return Str::random(32);
    }

    public function verifyTxt(string $domain, string $token): bool
    {
        $host = '_foreverloved-verify.'.$domain;

        try {
            $records = dns_get_record($host, DNS_TXT);
        } catch (\Throwable $e) {
            return false;
        }

        if (! is_array($records)) {
            return false;
        }

        foreach ($records as $record) {
            if (($record['txt'] ?? null) === $token) {
                return true;
            }
        }

        return false;
    }
}
