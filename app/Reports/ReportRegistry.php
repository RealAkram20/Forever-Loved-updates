<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Contracts\Report;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The catalogue.
 *
 * Reports are looked up by key *within an audience*. An admin-only key requested on the
 * reseller route does not 403 — it 404s, because confirming that a report exists is itself
 * a small leak, and there is no reason for a reseller's URL space to acknowledge it.
 */
class ReportRegistry
{
    public const AUDIENCE_ADMIN = 'admin';

    public const AUDIENCE_RESELLER = 'reseller';

    /**
     * @var array<string, array<string, class-string<Report>>>
     */
    private const MAP = [
        self::AUDIENCE_ADMIN => [
            'revenue' => Admin\RevenueReport::class,
            'subscriptions' => Admin\SubscriptionsReport::class,
            'memorials' => Admin\MemorialsReport::class,
            'engagement' => Admin\EngagementReport::class,
            'users' => Admin\UsersReport::class,
            // Super-admin only, enforced in the report classes themselves — a plain admin
            // never sees these two in the catalogue and 404s on the URL.
            'reseller-roster' => Admin\ResellerRosterReport::class,
            'reseller-billing' => Admin\ResellerBillingReport::class,
        ],
        self::AUDIENCE_RESELLER => [
            'memorials' => Reseller\ClientMemorialsReport::class,
            'clients' => Reseller\ClientsReport::class,
            // Tier-gated: renders the pitch rather than a 403, and refuses the download.
            'engagement' => Reseller\EngagementReport::class,
            // Hidden entirely unless they take their own payments.
            'revenue' => Reseller\RevenueReport::class,
            'account' => Reseller\AccountStatementReport::class,
        ],
    ];

    /**
     * Every report in an audience that this user may see, in catalogue order.
     *
     * @return Collection<int, Report>
     */
    public function for(string $audience, User $user): Collection
    {
        return collect(self::MAP[$audience] ?? [])
            ->map(fn (string $class) => $this->build($class))
            ->filter(fn (Report $report) => $report->availableTo($user))
            ->values();
    }

    /**
     * Reports grouped for the catalogue page.
     *
     * @return Collection<string, Collection<int, Report>>
     */
    public function grouped(string $audience, User $user): Collection
    {
        return $this->for($audience, $user)->groupBy(fn (Report $report) => $report->group());
    }

    public function resolve(string $audience, string $key, User $user): Report
    {
        $class = self::MAP[$audience][$key] ?? null;

        if (! $class) {
            throw new NotFoundHttpException('No such report.');
        }

        $report = $this->build($class);

        if (! $report->availableTo($user)) {
            throw new NotFoundHttpException('No such report.');
        }

        return $report;
    }

    /**
     * Built through the container so tenant-scoped reports receive the Reseller that
     * EnsureResellerActive bound from the authenticated user — never from the request.
     */
    private function build(string $class): Report
    {
        return app($class);
    }
}
