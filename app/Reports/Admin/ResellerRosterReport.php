<?php

namespace App\Reports\Admin;

use App\Models\Reseller;
use App\Models\User;
use App\Reports\BaseReport;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * The reseller book: who they are, what they are using, and who is about to need more.
 *
 * A snapshot, not a windowed report — "83% of their quota" is a fact about today, and
 * filtering it by a date range would produce a number that means nothing.
 */
class ResellerRosterReport extends BaseReport
{
    /** Where a quota stops being headroom and starts being a conversation. */
    private const QUOTA_WARNING_PERCENT = 80;

    public function key(): string
    {
        return 'reseller-roster';
    }

    public function title(): string
    {
        return 'Reseller roster';
    }

    public function description(): string
    {
        return 'Every reseller, what they are using against their tier, and who is close to their limit.';
    }

    public function group(): string
    {
        return 'Resellers';
    }

    public function usesDateWindow(): bool
    {
        return false;
    }

    public function availableTo(User $user): bool
    {
        // Genuinely super-admin, matching the middleware on the rest of the reseller
        // program — this exposes every reseller's commercial position at once.
        return $user->hasRole('super-admin');
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('name', 'Reseller', '14%'),
            ReportColumn::text('owner', 'Owner', '12%'),
            ReportColumn::text('email', 'Owner email', '15%', secondary: true),
            ReportColumn::text('tier', 'Tier', '9%'),
            ReportColumn::text('status', 'Status', '7%'),
            ReportColumn::number('profiles_used', 'Profiles used', '8%'),
            ReportColumn::text('allowance', 'Included', '7%'),
            ReportColumn::percent('quota_percent', 'Quota used', '8%'),
            ReportColumn::number('storage_mb', 'Storage MB', '8%', secondary: true),
            ReportColumn::percent('storage_percent', 'Storage used', '8%'),
            ReportColumn::number('clients', 'Clients', '6%'),
            ReportColumn::number('plans', 'Plans', '6%', secondary: true),
            ReportColumn::text('domain', 'Custom domain', '12%'),
            ReportColumn::date('joined', 'Joined', '8%', secondary: true),
            ReportColumn::text('billing', 'Billing', '9%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $resellers = $this->all();

        $overQuota = $resellers->filter(fn (Reseller $r) => $r->memorialAllowance() !== null && $r->memorialsUsed() > $r->memorialAllowance())->count();
        $nearQuota = $resellers->filter(function (Reseller $r) {
            $allowance = $r->memorialAllowance();

            return $allowance
                && $r->memorialsUsed() <= $allowance
                && ($r->memorialsUsed() / $allowance) * 100 >= self::QUOTA_WARNING_PERCENT;
        })->count();
        $nearStorage = $resellers->filter(fn (Reseller $r) => ($r->storagePercentUsed() ?? 0) >= self::QUOTA_WARNING_PERCENT)->count();
        $unverified = $resellers->filter(fn (Reseller $r) => $r->custom_domain && ! $r->hasVerifiedCustomDomain())->count();

        return [
            ReportStat::make('Resellers', $this->number($resellers->count()),
                $this->number($resellers->where('status', Reseller::STATUS_ACTIVE)->count()).' active'),
            ReportStat::make('Suspended', $this->number($resellers->where('status', Reseller::STATUS_SUSPENDED)->count())),
            ReportStat::make('Memorials hosted', $this->number($resellers->sum(fn (Reseller $r) => $r->memorialsUsed()))),
            ReportStat::make(
                'Over their allowance',
                $this->number($overQuota),
                'Billable as overage',
                $overQuota > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make(
                'Approaching their limit',
                $this->number($nearQuota),
                self::QUOTA_WARNING_PERCENT.'% or more of quota used',
                $nearQuota > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make(
                'Custom domains unverified',
                $this->number($unverified),
                $nearStorage > 0 ? $this->number($nearStorage).' also near their storage limit' : null,
            ),
        ];
    }

    public function total(ReportFilters $filters): int
    {
        return Reseller::count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        // Materialised: every column here comes from a model method that runs its own
        // query (memorialsUsed, storageUsedBytes, amountDue). The roster is tens of rows,
        // not thousands, so this is bounded — but it is why this report is not streamed.
        $rows = $this->all()
            ->sortByDesc(fn (Reseller $reseller) => $reseller->memorialsUsed())
            ->map(fn (Reseller $reseller) => $this->shape([
                'name' => $reseller->name,
                'owner' => $reseller->owner?->name,
                'email' => $reseller->owner?->email,
                'tier' => $reseller->tier?->name ?? 'No tier',
                'status' => ucfirst((string) $reseller->status),
                'profiles_used' => $reseller->memorialsUsed(),
                // "Unlimited" rather than an empty cell or a zero: a null allowance is a
                // deliberate commercial state, not missing data.
                'allowance' => $reseller->memorialAllowance() === null ? 'Unlimited' : (string) $reseller->memorialAllowance(),
                'quota_percent' => $this->quotaPercent($reseller),
                'storage_mb' => (int) round($reseller->storageUsedBytes() / 1048576),
                'storage_percent' => $reseller->storagePercentUsed(),
                // Reseller::staff() is every user attached to the tenant, staff and clients
                // alike. Counting it raw would report their own employees as customers, so
                // this narrows to the 'user' role — the same definition the reseller
                // dashboard's client count already uses.
                'clients' => $reseller->staff()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'user'))
                    ->count(),
                'plans' => $reseller->plans()->count(),
                'domain' => $this->domainLabel($reseller),
                'joined' => $reseller->created_at,
                'billing' => $this->billingLabel($reseller),
            ]))
            ->values();

        return LazyCollection::make($rows->all());
    }

    private function quotaPercent(Reseller $reseller): ?float
    {
        $allowance = $reseller->memorialAllowance();

        return $allowance ? round(($reseller->memorialsUsed() / $allowance) * 100) : null;
    }

    private function domainLabel(Reseller $reseller): string
    {
        if (! $reseller->custom_domain) {
            return 'Subdomain only';
        }

        return $reseller->custom_domain.' ('.$reseller->custom_domain_status.')';
    }

    private function billingLabel(Reseller $reseller): string
    {
        return match ($reseller->billingStatus()) {
            Reseller::BILLING_NOT_STARTED => 'Not started',
            Reseller::BILLING_OVERDUE => 'Overdue',
            Reseller::BILLING_DUE_SOON => 'Due soon',
            default => 'Active',
        };
    }

    /**
     * @return Collection<int, Reseller>
     */
    private function all()
    {
        return Reseller::with(['owner:id,name,email', 'tier'])->get();
    }
}
