<?php

namespace App\Reports\Admin;

use App\Models\Reseller;
use App\Models\ResellerPayment;
use App\Models\User;
use App\Reports\BaseReport;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * What resellers have paid us, and what is still outstanding.
 */
class ResellerBillingReport extends BaseReport
{
    public function key(): string
    {
        return 'reseller-billing';
    }

    public function title(): string
    {
        return 'Reseller billing';
    }

    public function description(): string
    {
        return 'Payments received from resellers, plus what each one currently owes.';
    }

    public function group(): string
    {
        return 'Resellers';
    }

    public function dateWindowNote(): ?string
    {
        return 'By payment date. The outstanding figures above are current as of now.';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function columns(): array
    {
        return [
            ReportColumn::date('paid_at', 'Paid', '9%'),
            ReportColumn::text('reseller', 'Reseller', '15%'),
            ReportColumn::text('tier', 'Tier', '11%'),
            ReportColumn::money('tier_price', 'Tier price', 'currency', '10%'),
            ReportColumn::number('overage_profiles', 'Overage profiles', '9%', secondary: true),
            ReportColumn::money('overage_amount', 'Overage', 'currency', '10%', secondary: true),
            ReportColumn::money('amount', 'Total paid', 'currency', '11%'),
            ReportColumn::date('period_start', 'Period from', '9%'),
            ReportColumn::date('period_end', 'Period to', '9%'),
            ReportColumn::text('method', 'Method', '9%'),
            ReportColumn::text('reference', 'Reference', '11%', secondary: true),
            ReportColumn::text('recorded_by', 'Recorded by', '11%', secondary: true),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $collected = $this->query($filters)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total) => (float) $total)
            ->all();

        $overage = (float) $this->query($filters)->sum('overage_amount');

        $resellers = Reseller::with('tier')->get();
        $outstanding = $resellers->sum(fn (Reseller $r) => $r->amountDue());
        $overdue = $resellers->filter(fn (Reseller $r) => $r->billingStatus() === Reseller::BILLING_OVERDUE);
        $dueSoon = $resellers->filter(fn (Reseller $r) => $r->billingStatus() === Reseller::BILLING_DUE_SOON);

        return [
            ReportStat::make('Collected in period', $this->moneyByCurrency($collected)),
            ReportStat::make('Of which overage', $this->money($overage, $this->defaultCurrency()), 'Profiles beyond the included allowance'),
            ReportStat::make('Currently outstanding', $this->money($outstanding, $this->defaultCurrency()), 'Across every reseller, as of now'),
            ReportStat::make(
                'Overdue resellers',
                $this->number($overdue->count()),
                $overdue->isNotEmpty() ? $this->money($overdue->sum(fn (Reseller $r) => $r->amountDue()), $this->defaultCurrency()).' unpaid' : null,
                $overdue->isNotEmpty() ? ReportStat::TONE_DANGER : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make(
                'Renewing within 30 days',
                $this->number($dueSoon->count()),
                $dueSoon->isNotEmpty() ? $this->money($dueSoon->sum(fn (Reseller $r) => $r->amountDue()), $this->defaultCurrency()).' due' : null,
                $dueSoon->isNotEmpty() ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
        ];
    }

    public function total(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        return $this->query($filters)
            ->with(['reseller:id,name', 'recordedBy:id,name'])
            ->orderByDesc('paid_at')
            ->lazy(500)
            ->map(fn (ResellerPayment $payment) => $this->shape([
                'paid_at' => $payment->paid_at,
                'reseller' => $payment->reseller?->name,
                // tier_name is denormalised onto the payment on purpose — it records what
                // they were on when they paid, which is what an invoice has to say even
                // after they move tiers.
                'tier' => $payment->tier_name,
                'tier_price' => $payment->tier_annual_price,
                'overage_profiles' => $payment->overage_profiles,
                'overage_amount' => $payment->overage_amount,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'period_start' => $payment->period_start,
                'period_end' => $payment->period_end,
                'method' => ResellerPayment::methods()[$payment->method] ?? ucfirst((string) $payment->method),
                'reference' => $payment->reference,
                'recorded_by' => $payment->recordedBy?->name,
            ]));
    }

    private function query(ReportFilters $filters): Builder
    {
        return $filters->applyTo(ResellerPayment::query(), 'paid_at');
    }
}
