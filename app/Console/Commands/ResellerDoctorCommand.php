<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Support\ResellerHealthProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Diagnoses why a reseller address does or does not work, in the order things fail
 * in the real world: app configuration, then DNS, then TLS, then HTTP. Every check
 * points at the runbook section that fixes it (RESELLER-PRODUCTION-CHECKLIST.md §12),
 * because the fixes are all one-time infrastructure work outside this codebase.
 */
class ResellerDoctorCommand extends Command
{
    protected $signature = 'reseller:doctor {slug? : Also probe this reseller\'s own address}';

    protected $description = 'Check the DNS, TLS and routing infrastructure reseller subdomains depend on';

    /** @var array{0: string, 1: string, 2: string}[] label, PASS/FAIL/SKIP, detail */
    private array $rows = [];

    private bool $failed = false;

    public function handle(ResellerHealthProbe $probe): int
    {
        // Reset: the container hands back the same command instance for a second run in
        // the same process, and stale rows would make a passing run report the previous
        // run's failures.
        $this->rows = [];
        $this->failed = false;

        $base = (string) config('reseller.domain');

        // -- Configuration ------------------------------------------------------
        $this->check('APP_URL has no path', Reseller::hostRoutingAvailable(),
            'host routing available', 'APP_URL contains a path — see checklist §1');

        $this->check('Reseller base domain', $base !== '' && str_contains($base, '.'),
            $base, "'$base' cannot mint subdomains — see checklist §2");

        $this->check('Subdomain routing claimed available', Reseller::subdomainRoutingAvailable(),
            'yes — the UI is handing out {slug}.'.$base.' addresses',
            'no — the UI is falling back to /r/{slug} paths');

        // -- DNS ----------------------------------------------------------------
        $apexIp = $probe->apexIp();
        $this->check('Apex DNS ('.$base.')', $apexIp !== null,
            (string) $apexIp, 'does not resolve');

        $wildcardIp = $probe->wildcardResolvedIp();
        $this->check('Wildcard DNS (*.'.$base.')', $wildcardIp !== null,
            (string) $wildcardIp, 'NXDOMAIN — every reseller subdomain is dead. Runbook §12.1');

        // Same verdict the admin roster's banner reads, so running this by hand after
        // adding the record clears the warning rather than leaving it up for an hour.
        // Best-effort: this cache store is the database, and a diagnostic tool that dies
        // because the database is down is useless at exactly the moment it is needed.
        try {
            Cache::put(ResellerHealthProbe::WILDCARD_DEAD_CACHE_KEY, $wildcardIp === null, now()->addDay());
        } catch (\Throwable) {
            $this->skip('Cache the verdict for the admin banner', 'cache unavailable — the probe results above still stand');
        }

        if ($wildcardIp !== null && $apexIp !== null) {
            $this->check('Wildcard points at the apex server', $wildcardIp === $apexIp,
                'matches', "wildcard → $wildcardIp but apex → $apexIp — traffic goes to the wrong box");
        }

        // -- TLS + HTTP ---------------------------------------------------------
        if ($wildcardIp !== null) {
            $sampleHost = 'probe.'.$base;
            $tls = $probe->tlsReport($sampleHost, $apexIp);
            $this->check('Wildcard TLS certificate', $tls['ok'],
                sprintf('%s, expires %s', $tls['issuer'] ?? 'unknown issuer', $tls['expires_at'] ?? '?'),
                ($tls['error'] ?? 'handshake failed').' — likely the letsencrypt-dns resolver is missing on the proxy. Runbook §12.3');
        } else {
            $this->skip('Wildcard TLS certificate', 'skipped — no wildcard DNS to probe through');
        }

        if ($apexIp !== null) {
            $apexUrl = rtrim((string) config('app.url'), '/');
            $this->check('Apex HTTP', $probe->httpOk($apexUrl), 'responding', "$apexUrl unreachable or 5xx");
        }

        // -- A specific reseller ------------------------------------------------
        if (($slug = $this->argument('slug')) !== null) {
            $reseller = Reseller::where('slug', $slug)->first();

            if (! $reseller) {
                $this->skip("Reseller '$slug'", 'no reseller with that slug');
                $this->failed = true;
            } else {
                $this->probeHost($probe, $reseller->slug.'.'.$base, "Subdomain ({$reseller->slug}.$base)");

                if ($reseller->hasVerifiedCustomDomain()) {
                    $this->probeHost($probe, $reseller->custom_domain, "Custom domain ({$reseller->custom_domain})");
                }
            }
        }

        $this->table(['Check', 'Result', 'Detail'], $this->rows);

        if ($this->failed) {
            $this->warn('Fixes for every failure above are in RESELLER-PRODUCTION-CHECKLIST.md §12.');
        } else {
            $this->info('All checks passed.');
        }

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function probeHost(ResellerHealthProbe $probe, string $host, string $label): void
    {
        $ip = $probe->resolveIp($host);
        $this->check("$label DNS", $ip !== null, (string) $ip, 'does not resolve');

        if ($ip === null) {
            $this->skip("$label TLS", 'skipped — no DNS');

            return;
        }

        $tls = $probe->tlsReport($host, $ip);
        $this->check("$label TLS", $tls['ok'],
            sprintf('%s, expires %s', $tls['issuer'] ?? 'unknown issuer', $tls['expires_at'] ?? '?'),
            (string) ($tls['error'] ?? 'handshake failed'));

        $this->check("$label HTTP", $probe->httpOk('https://'.$host), 'responding', 'unreachable or 5xx');
    }

    private function check(string $label, bool $ok, string $passDetail, string $failDetail): void
    {
        $this->rows[] = [$label, $ok ? '<info>PASS</info>' : '<error>FAIL</error>', $ok ? $passDetail : $failDetail];
        $this->failed = $this->failed || ! $ok;
    }

    private function skip(string $label, string $detail): void
    {
        $this->rows[] = [$label, '<comment>SKIP</comment>', $detail];
    }
}
