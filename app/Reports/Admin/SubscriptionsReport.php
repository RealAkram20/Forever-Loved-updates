<?php

namespace App\Reports\Admin;

use App\Models\User;
use App\Models\UserSubscription;
use App\Reports\BaseReport;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * What is running, what is lapsing, and what that is worth.
 *
 * The rows are windowed by when each subscription started, but the headline figures are
 * deliberately "as of now" — how many are active today does not depend on which month you
 * happen to be looking at, and computing it inside the window would make renewals look
 * like churn.
 */
class SubscriptionsReport extends BaseReport
{
    public function key(): string
    {
        return 'subscriptions';
    }

    public function title(): string
    {
        return 'Subscriptions';
    }

    public function description(): string
    {
        return 'Active, overdue and expiring plans, and the revenue standing behind them.';
    }

    public function group(): string
    {
        return 'Money';
    }

    public function dateWindowNote(): ?string
    {
        return 'Rows are filtered by start date. The figures above are current as of now, whatever window is selected.';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin']);
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('customer', 'Customer', '15%'),
            ReportColumn::text('email', 'Email', '16%', secondary: true),
            ReportColumn::text('memorial', 'Memorial', '15%'),
            ReportColumn::text('plan', 'Plan', '11%'),
            ReportColumn::money('price', 'Price', 'currency', '9%'),
            ReportColumn::date('starts_at', 'Started', '9%'),
            ReportColumn::date('ends_at', 'Ends', '9%'),
            ReportColumn::number('days_left', 'Days left', '7%'),
            ReportColumn::text('status', 'Status', '9%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $currency = $this->defaultCurrency();

        $active = UserSubscription::active()->count();
        $overdue = UserSubscription::overdue()->count();
        $expiring30 = UserSubscription::expiringSoon(30)->count();
        $startedInWindow = $this->query($filters)->count();

        // What lapses if nobody renews: the plan price behind every overdue or
        // soon-to-expire subscription. This is the number that justifies a reminder run.
        // Each branch is wrapped in its own closure so the scopes' several where clauses
        // group correctly — chained bare, the OR would bind to only the last one and
        // quietly widen the figure to most of the table.
        $atRisk = (float) UserSubscription::query()
            ->where(function (Builder $q) {
                $q->where(fn (Builder $overdue) => $overdue->overdue())
                    ->orWhere(fn (Builder $soon) => $soon->expiringSoon(30));
            })
            ->join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price');

        return [
            ReportStat::make('Active now', $this->number($active)),
            ReportStat::make('Started in period', $this->number($startedInWindow)),
            ReportStat::make(
                'Expiring within 30 days',
                $this->number($expiring30),
                null,
                $expiring30 > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make(
                'Overdue',
                $this->number($overdue),
                null,
                $overdue > 0 ? ReportStat::TONE_DANGER : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make('Revenue at risk', $this->money($atRisk, $currency), 'Overdue plus expiring within 30 days'),
        ];
    }

    public function total(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        $currency = $this->defaultCurrency();

        return $this->query($filters)
            ->with(['user:id,name,email', 'memorial:id,full_name', 'plan:id,name,price'])
            ->orderByRaw('ends_at IS NULL')
            ->orderBy('ends_at')
            ->lazy(500)
            ->map(fn (UserSubscription $subscription) => $this->shape([
                'customer' => $subscription->user?->name,
                'email' => $subscription->user?->email,
                'memorial' => $subscription->memorial?->full_name,
                'plan' => $subscription->plan?->name,
                'price' => $subscription->plan?->price,
                'currency' => $currency,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'days_left' => $subscription->daysUntilExpiry(),
                'status' => $this->statusLabel($subscription),
            ]));
    }

    /**
     * The stored status alone is misleading: a row still marked 'active' whose end date
     * has passed is overdue, and reading it off the column would report it as healthy.
     */
    private function statusLabel(UserSubscription $subscription): string
    {
        return match (true) {
            $subscription->isOverdue() => 'Overdue',
            $subscription->isExpired() => 'Expired',
            $subscription->isExpiringSoon(30) => 'Expiring soon',
            $subscription->isActive() => 'Active',
            default => ucfirst((string) $subscription->status),
        };
    }

    private function query(ReportFilters $filters): Builder
    {
        return $filters->applyTo(UserSubscription::query(), 'starts_at');
    }
}
