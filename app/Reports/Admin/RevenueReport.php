<?php

namespace App\Reports\Admin;

use App\Models\PaymentOrder;
use App\Models\User;
use App\Reports\BaseReport;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Every checkout the platform has taken, and what came of it.
 */
class RevenueReport extends BaseReport
{
    public function key(): string
    {
        return 'revenue';
    }

    public function title(): string
    {
        return 'Revenue';
    }

    public function description(): string
    {
        return 'Every order placed, what it was for, and whether the money arrived.';
    }

    public function group(): string
    {
        return 'Money';
    }

    public function dateWindowNote(): ?string
    {
        return 'By order date — when checkout was started. The payment date of each order is shown as its own column.';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin']);
    }

    public function columns(): array
    {
        return [
            ReportColumn::date('date', 'Order date', '9%'),
            ReportColumn::text('reference', 'Reference', '12%'),
            ReportColumn::text('customer', 'Customer', '14%'),
            ReportColumn::text('email', 'Email', '15%', secondary: true),
            ReportColumn::text('memorial', 'Memorial', '14%'),
            ReportColumn::text('plan', 'Plan', '10%'),
            ReportColumn::money('amount', 'Amount', 'currency', '10%'),
            ReportColumn::text('status', 'Status', '8%'),
            ReportColumn::text('gateway', 'Gateway', '8%', secondary: true),
            ReportColumn::text('method', 'Method', '8%', secondary: true),
            ReportColumn::datetime('paid_at', 'Paid', '10%'),
            ReportColumn::text('seller', 'Sold by', '10%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $base = $this->query($filters);

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as orders')
            ->groupBy('status')
            ->pluck('orders', 'status');

        $all = (int) $byStatus->sum();
        $completed = (int) ($byStatus['completed'] ?? 0);
        $failed = (int) ($byStatus['failed'] ?? 0) + (int) ($byStatus['cancelled'] ?? 0);

        // Grouped by currency, never summed across it: every payment_orders row carries its
        // own currency, so a single total would silently add shillings to dollars.
        $grossByCurrency = (clone $base)
            ->where('status', 'completed')
            ->selectRaw('currency, SUM(amount) as gross')
            ->groupBy('currency')
            ->pluck('gross', 'currency')
            ->map(fn ($gross) => (float) $gross)
            ->all();

        $viaResellers = (int) (clone $base)->where('status', 'completed')->whereNotNull('reseller_id')->count();

        return array_filter([
            ReportStat::make('Gross collected', $this->moneyByCurrency($grossByCurrency), 'Completed orders only'),
            ReportStat::make('Completed orders', $this->number($completed)),
            $this->averageOrderValue($grossByCurrency, $completed),
            ReportStat::make(
                'Failed or cancelled',
                $this->number($failed),
                $failed > 0 ? $this->ratio($failed, $all).' of all orders' : null,
                $failed > 0 && $all > 0 && ($failed / $all) > 0.3 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make('Checkout completion', $this->ratio($completed, $all), $this->number($all).' orders started'),
            ReportStat::make(
                'Sold through resellers',
                $this->number($viaResellers),
                $completed > 0 ? $this->ratio($viaResellers, $completed).' of completed orders' : null,
            ),
        ]);
    }

    /**
     * Only meaningful within a single currency. With takings in more than one, an "average"
     * is a number with no unit — better to say so than to print something divisible.
     */
    private function averageOrderValue(array $grossByCurrency, int $completed): ?ReportStat
    {
        if ($completed === 0) {
            return ReportStat::make('Average order', '—');
        }

        if (count($grossByCurrency) > 1) {
            return ReportStat::make('Average order', '—', 'Takings span several currencies');
        }

        $currency = array_key_first($grossByCurrency);

        return ReportStat::make('Average order', $this->money(reset($grossByCurrency) / $completed, $currency));
    }

    public function total(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        return $this->query($filters)
            ->with(['user:id,name,email', 'plan:id,name', 'memorial:id,full_name', 'reseller:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            // lazy() chunks the result rather than hydrating every order at once, and
            // eager-loads the four relations per chunk instead of per row.
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
                'gateway' => ucfirst((string) $order->payment_gateway),
                'method' => $order->payment_method,
                'paid_at' => $order->paid_at,
                // Named "Direct" rather than left blank: an empty cell reads as missing
                // data, when it actually means the platform sold it itself.
                'seller' => $order->reseller?->name ?? 'Direct',
            ]));
    }

    private function query(ReportFilters $filters): Builder
    {
        return $filters->applyTo(PaymentOrder::query(), 'created_at');
    }
}
