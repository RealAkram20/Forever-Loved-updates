<?php

namespace App\Helpers;

use App\Models\Memorial;
use App\Models\PaymentOrder;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;

class SubscriptionGuard
{
    /**
     * How long a pending order blocks a new attempt. Inside this window the
     * customer is probably still in the gateway's payment window, so a second
     * order would risk a double charge. Past it the attempt is treated as
     * abandoned and a retry supersedes it — otherwise one closed tab would lock
     * the memorial out of paying until reconciliation expires the order 48h later.
     */
    private const PENDING_LOCK_MINUTES = 10;

    /**
     * Determine whether a new payment can be created for a memorial on a given plan.
     *
     * @return array{allowed: bool, reason: string|null, type: 'new'|'upgrade'|'renewal'|null, existing_subscription: UserSubscription|null}
     */
    public static function canCreatePayment(Memorial $memorial, SubscriptionPlan $plan): array
    {
        $activeSub = UserSubscription::where('memorial_id', $memorial->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->with('plan')
            ->first();

        if ($activeSub) {
            if ($activeSub->subscription_plan_id === $plan->id) {
                return [
                    'allowed' => false,
                    'reason' => 'This memorial already has an active subscription for the ' . $plan->name . ' plan. You can upgrade to a higher plan or renew when it expires.',
                    'type' => null,
                    'existing_subscription' => $activeSub,
                ];
            }

            $currentPrice = (float) ($activeSub->plan->price ?? 0);
            $requestedPrice = (float) $plan->price;

            if ($requestedPrice > $currentPrice) {
                return [
                    'allowed' => true,
                    'reason' => null,
                    'type' => 'upgrade',
                    'existing_subscription' => $activeSub,
                ];
            }

            return [
                'allowed' => false,
                'reason' => 'This memorial already has an active subscription on the ' . ($activeSub->plan->name ?? 'current') . ' plan. You can only upgrade to a higher plan.',
                'type' => null,
                'existing_subscription' => $activeSub,
            ];
        }

        $overdueSub = UserSubscription::where('memorial_id', $memorial->id)
            ->where('status', 'overdue')
            ->first();

        if ($overdueSub) {
            return [
                'allowed' => true,
                'reason' => null,
                'type' => 'renewal',
                'existing_subscription' => $overdueSub,
            ];
        }

        $expiredSub = UserSubscription::where('memorial_id', $memorial->id)
            ->whereIn('status', ['active', 'expired'])
            ->where('ends_at', '<=', now())
            ->first();

        if ($expiredSub) {
            return [
                'allowed' => true,
                'reason' => null,
                'type' => 'renewal',
                'existing_subscription' => $expiredSub,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'type' => 'new',
            'existing_subscription' => null,
        ];
    }

    /**
     * Check if there is already a pending payment order for this memorial + plan.
     *
     * @param  int|null  $withinMinutes  Only count orders created this recently.
     */
    public static function hasPendingOrder(Memorial $memorial, SubscriptionPlan $plan, ?int $withinMinutes = null): bool
    {
        return PaymentOrder::where('memorial_id', $memorial->id)
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'pending')
            ->when($withinMinutes !== null, fn ($q) => $q->where('created_at', '>', now()->subMinutes($withinMinutes)))
            ->exists();
    }

    /**
     * Expire all currently active subscriptions on a memorial (used during upgrades).
     */
    public static function expireActiveSubscriptions(Memorial $memorial): void
    {
        UserSubscription::where('memorial_id', $memorial->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->update(['status' => 'expired', 'ends_at' => now()]);
    }

    /**
     * Full guard: checks both subscription state and pending orders.
     *
     * @return array{allowed: bool, reason: string|null, type: 'new'|'upgrade'|'renewal'|null, existing_subscription: UserSubscription|null}
     */
    public static function validatePayment(Memorial $memorial, SubscriptionPlan $plan): array
    {
        $check = static::canCreatePayment($memorial, $plan);

        if (! $check['allowed']) {
            return $check;
        }

        if (static::hasPendingOrder($memorial, $plan, self::PENDING_LOCK_MINUTES)) {
            return [
                'allowed' => false,
                'reason' => 'A payment for this memorial on the ' . $plan->name . ' plan is still going through. Finish it in the payment window, or wait ' . self::PENDING_LOCK_MINUTES . ' minutes and try again.',
                'type' => null,
                'existing_subscription' => $check['existing_subscription'],
            ];
        }

        return $check;
    }
}
