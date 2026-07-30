<?php

namespace App\Reports\Reseller;

use App\Models\PaymentOrder;
use App\Models\User;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use App\Reports\TenantScopedReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * What the reseller has sold through their own Pesapal account.
 *
 * Only meaningful for a reseller taking their own payments. One on platform billing has no
 * sales of their own, so the report is hidden from them entirely rather than shown as a
 * table of zeros they would reasonably read as a bug.
 */
class RevenueReport extends TenantScopedReport
{
    public function key(): string
    {
        return 'revenue';
    }

    public function title(): string
    {
        return 'Your sales';
    }

    public function description(): string
    {
        return 'Orders your clients have paid you for, and whether the money arrived.';
    }

    public function group(): string
    {
        return 'Your business';
    }

    public function dateWindowNote(): ?string
    {
        return 'By order date — when checkout was started.';
    }

    public function availableTo(User $user): bool
    {
        return parent::availableTo($user) && $this->reseller->pesapal_enabled;
    }

    public function columns(): array
    {
        return [
            ReportColumn::date('date', 'Order date', '11%'),
            ReportColumn::text('reference', 'Reference', '14%'),
            ReportColumn::text('customer', 'Client', '15%'),
            ReportColumn::text('email', 'Email', '16%', secondary: true),
            ReportColumn::text('memorial', 'Memorial', '15%'),
            ReportColumn::text('plan', 'Plan', '11%'),
            ReportColumn::money('amount', 'Amount', 'currency', '11%'),
            ReportColumn::text('status', 'Status', '9%'),
            ReportColumn::text('method', 'Method', '9%', secondary: true),
            ReportColumn::datetime('paid_at', 'Paid', '11%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $base = $this->query($filters);

        $all = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $failed = (clone $base)->whereIn('status', ['failed', 'cancelled'])->count();

        $grossByCurrency = (clone $base)
            ->where('status', 'completed')
            ->selectRaw('currency, SUM(amount) as gross')
            ->groupBy('currency')
            ->pluck('gross', 'currency')
            ->map(fn ($gross) => (float) $gross)
            ->all();

        $bestPlan = (clone $base)
            ->where('status', 'completed')
            ->join('subscription_plans', 'payment_orders.subscription_plan_id', '=', 'subscription_plans.id')
            ->selectRaw('subscription_plans.name, COUNT(*) as sold')
            ->groupBy('subscription_plans.name')
            ->orderByDesc('sold')
            ->first();

        return [
            ReportStat::make('Collected', $this->moneyByCurrency($grossByCurrency), 'Completed orders only'),
            ReportStat::make('Orders paid', $this->number($completed)),
            ReportStat::make('Checkout completion', $this->ratio($completed, $all), $this->number($all).' orders started'),
            ReportStat::make(
                'Failed or cancelled',
                $this->number($failed),
                null,
                $failed > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make('Best seller', $bestPlan?->name ?? '—', $bestPlan ? $this->number($bestPlan->sold).' sold' : null),
        ];
    }

    public function total(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        return $this->query($filters)
            ->with(['user:id,name,email', 'plan:id,name', 'memorial:id,full_name'])
            ->orderByDesc('created_at')
            ->lazy(500)
            ->map(fn (PaymentOrder $order) => $this->shape([
                'date' => $order->created_at,
                'reference' => $order->merchant_reference,
                'customer' => $order->user?->name,
                'email' => $order->user?->email,
                'memorial' => $order->memorial?->full_name,
                'plan' => $order->plan?->name,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'status' => ucfirst((string) $order->status),
                'method' => $order->payment_method,
                'paid_at' => $order->paid_at,
            ]));
    }

    private function query(ReportFilters $filters): Builder
    {
        // Column qualified: the best-seller stat joins subscription_plans, which also has
        // a reseller_id, and an unqualified name is an ambiguous-column error there.
        return $filters->applyTo(
            PaymentOrder::query()->where('payment_orders.reseller_id', $this->reseller->id),
            'payment_orders.created_at',
        );
    }
}
