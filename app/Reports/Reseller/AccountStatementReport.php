<?php

namespace App\Reports\Reseller;

use App\Models\Reseller;
use App\Models\ResellerPayment;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use App\Reports\TenantScopedReport;
use Illuminate\Support\LazyCollection;

/**
 * What the reseller is using, what they owe, and everything they have paid us.
 *
 * Their own accounting record, so it is never tier-gated — charging a business for the
 * ability to see its own invoices would be indefensible. The payment rows carry
 * references and period dates precisely because this gets handed to a bookkeeper.
 */
class AccountStatementReport extends TenantScopedReport
{
    public function key(): string
    {
        return 'account';
    }

    public function title(): string
    {
        return 'Account & quota statement';
    }

    public function description(): string
    {
        return 'Your tier, what you have used against it, what is due, and every payment you have made.';
    }

    public function group(): string
    {
        return 'Your business';
    }

    public function usesDateWindow(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            ReportColumn::date('paid_at', 'Paid', '11%'),
            ReportColumn::date('period_start', 'Period from', '12%'),
            ReportColumn::date('period_end', 'Period to', '12%'),
            ReportColumn::text('tier', 'Tier', '13%'),
            ReportColumn::money('tier_price', 'Tier price', 'currency', '12%'),
            ReportColumn::number('overage_profiles', 'Extra profiles', '10%'),
            ReportColumn::money('overage_amount', 'Extra charge', 'currency', '11%'),
            ReportColumn::money('amount', 'Total paid', 'currency', '11%'),
            ReportColumn::text('method', 'Method', '10%'),
            ReportColumn::text('reference', 'Reference', '12%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $reseller = $this->reseller;
        $allowance = $reseller->memorialAllowance();
        $used = $reseller->memorialsUsed();
        $storagePercent = $reseller->storagePercentUsed();
        $days = $reseller->daysUntilRenewal();

        return [
            ReportStat::make('Your tier', $reseller->tier?->name ?? 'No tier assigned'),
            ReportStat::make(
                'Memorial profiles',
                $allowance === null ? $this->number($used).' used' : "{$used} of {$allowance}",
                $allowance === null ? 'Unlimited on your tier' : $this->number(max(0, $allowance - $used)).' remaining',
                $this->quotaTone($used, $allowance),
            ),
            ReportStat::make(
                'Storage',
                $this->bytes($reseller->storageUsedBytes()),
                $reseller->storageLimitBytes() ? $this->percent($storagePercent).' of your limit' : 'No limit on your tier',
                $storagePercent !== null && $storagePercent >= 80 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make(
                'Current amount due',
                $this->money($reseller->amountDue(), $this->defaultCurrency()),
                $reseller->overageProfiles() > 0
                    ? $this->number($reseller->overageProfiles()).' profiles beyond your allowance'
                    : 'Your tier price',
            ),
            ReportStat::make(
                'Renewal',
                $this->renewalLabel($days),
                $reseller->billing_period_end?->format('j M Y'),
                match ($reseller->billingStatus()) {
                    Reseller::BILLING_OVERDUE => ReportStat::TONE_DANGER,
                    Reseller::BILLING_DUE_SOON => ReportStat::TONE_WARNING,
                    default => ReportStat::TONE_NEUTRAL,
                },
            ),
            ReportStat::make('Paid to date', $this->moneyByCurrency($this->paidByCurrency())),
        ];
    }

    private function quotaTone(int $used, ?int $allowance): string
    {
        if (! $allowance) {
            return ReportStat::TONE_NEUTRAL;
        }

        return match (true) {
            $used > $allowance => ReportStat::TONE_DANGER,
            ($used / $allowance) * 100 >= 80 => ReportStat::TONE_WARNING,
            default => ReportStat::TONE_NEUTRAL,
        };
    }

    /**
     * Null days means billing has never started — deliberately not rendered as "0 days",
     * which would read as due today.
     */
    private function renewalLabel(?int $days): string
    {
        return match (true) {
            $days === null => 'Not started',
            $days < 0 => abs($days).' days overdue',
            $days === 0 => 'Due today',
            default => "In {$days} days",
        };
    }

    /**
     * @return array<string, float>
     */
    private function paidByCurrency(): array
    {
        return $this->payments()
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    public function total(ReportFilters $filters): int
    {
        return $this->payments()->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        return $this->payments()
            ->orderByDesc('paid_at')
            ->lazy(200)
            ->map(fn (ResellerPayment $payment) => $this->shape([
                'paid_at' => $payment->paid_at,
                'period_start' => $payment->period_start,
                'period_end' => $payment->period_end,
                'tier' => $payment->tier_name,
                'tier_price' => $payment->tier_annual_price,
                'overage_profiles' => $payment->overage_profiles,
                'overage_amount' => $payment->overage_amount,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => ResellerPayment::methods()[$payment->method] ?? ucfirst((string) $payment->method),
                'reference' => $payment->reference,
            ]));
    }

    private function payments()
    {
        return ResellerPayment::query()->where('reseller_id', $this->reseller->id);
    }
}
