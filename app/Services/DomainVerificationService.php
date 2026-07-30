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

    /**
     * The TXT record host a reseller must create. Derived from the app's own domain rather
     * than a hardcoded brand string, and exposed publicly so the settings screen prints
     * exactly what this service will later look up — the two drifting apart would leave
     * resellers adding a record that never verifies.
     */
    public function txtHost(string $domain): string
    {
        return config('reseller.verification_prefix').'.'.$domain;
    }

    public function verifyTxt(string $domain, string $token): bool
    {
        $host = $this->txtHost($domain);

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
