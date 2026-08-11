<?php

namespace App\Services;

use App\Helpers\SubscriptionGuard;
use App\Models\Memorial;
use App\Models\PaymentOrder;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified Pesapal transaction status to a payment order.
 * Shared by the payment callback, the IPN endpoint, admin approval, and
 * scheduled reconciliation — callers map the returned outcome to their own
 * response (redirect, JSON, console).
 */
class PaymentResultProcessor
{
    public const OUTCOME_COMPLETED = 'completed';
    public const OUTCOME_ALREADY_COMPLETED = 'already_completed';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_PENDING = 'pending';
    public const OUTCOME_CANCELLED = 'cancelled';

    /**
     * @param  array  $status  Pesapal GetTransactionStatus payload
     * @return string one of the OUTCOME_* constants. COMPLETED means THIS call
     *                performed the completion (callers may notify the customer);
     *                ALREADY_COMPLETED means a concurrent caller had beaten us
     *                to it — the subscription is active but do not re-notify.
     */
    public function process(PaymentOrder $order, array $status, string $source): string
    {
        $pesapal = app(PesapalService::class);

        if ($pesapal->isPaymentCompleted($status)) {
            // Callback, IPN and reconciliation can race on the same order; the
            // row lock makes exactly one of them perform the transition.
            return DB::transaction(function () use ($order, $status) {
                $locked = PaymentOrder::lockForUpdate()->find($order->id);
                if (! $locked || $locked->isCompleted()) {
                    return self::OUTCOME_ALREADY_COMPLETED;
                }

                $memorial = $locked->memorial;
                if (! $memorial || ($memorial->status ?? Memorial::STATUS_ACTIVE) !== Memorial::STATUS_ACTIVE) {
                    $locked->update(['status' => 'cancelled']);

                    return self::OUTCOME_CANCELLED;
                }

                // What the gateway says was paid, against what we asked for. Nothing checked
                // this before: "completed" alone activated the subscription, whatever sum
                // actually changed hands. Underpayment leaves the order pending for a human
                // to look at rather than silently granting a paid plan.
                if ($this->isUnderpaid($locked, $status)) {
                    Log::warning('Pesapal reported a smaller amount than the order', [
                        'merchant_reference' => $locked->merchant_reference,
                        'expected' => $locked->amount,
                        'expected_currency' => $locked->currency,
                        'reported' => $status['amount'] ?? null,
                        'reported_currency' => $status['currency'] ?? null,
                    ]);

                    return self::OUTCOME_PENDING;
                }

                $locked->update([
                    'status' => 'completed',
                    // Stamped here, inside the same lock that performs the transition, so
                    // it records when the money was confirmed rather than when the row was
                    // last written. Revenue reports group on this.
                    'paid_at' => now(),
                    'confirmation_code' => $status['confirmation_code'] ?? null,
                ]);

                $this->activateSubscription($locked);

                return self::OUTCOME_COMPLETED;
            });
        }

        if ($pesapal->isPaymentFailed($status)) {
            $order->update(['status' => 'failed']);

            return self::OUTCOME_FAILED;
        }

        return self::OUTCOME_PENDING;
    }

    /**
     * True only when the gateway reported a figure we can compare and it falls short.
     *
     * Deliberately one-sided and tolerant: a response that omits `amount` (or names a
     * different currency, which we cannot convert) is not treated as underpayment, because
     * refusing to activate on a field the gateway simply did not send would strand paying
     * customers. Overpayment likewise activates. This catches the case that costs money.
     */
    private function isUnderpaid(PaymentOrder $order, array $status): bool
    {
        if (! isset($status['amount']) || ! is_numeric($status['amount'])) {
            return false;
        }

        $reportedCurrency = strtoupper(trim((string) ($status['currency'] ?? '')));
        if ($reportedCurrency !== '' && $reportedCurrency !== strtoupper((string) $order->currency)) {
            return false;
        }

        // A cent of slack for float representation and gateway rounding.
        return (float) $status['amount'] < ((float) $order->amount - 0.01);
    }

    public function activateSubscription(PaymentOrder $order): void
    {
        $plan = $order->plan;
        $user = $order->user;
        $memorial = $order->memorial;
        if (! $plan || ! $user || ! $memorial) {
            return;
        }

        $hasActive = UserSubscription::where('memorial_id', $memorial->id)
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();

        if ($hasActive) {
            return;
        }

        SubscriptionGuard::expireActiveSubscriptions($memorial);

        UserSubscription::where('memorial_id', $memorial->id)
            ->where('status', 'overdue')
            ->update(['status' => 'expired']);

        $startsAt = now();
        $endsAt = match ($plan->interval ?? 'monthly') {
            'monthly' => $startsAt->copy()->addMonth(),
            'yearly' => $startsAt->copy()->addYear(),
            default => null,
        };

        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'memorial_id' => $memorial->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'active',
            'payment_gateway' => $order->payment_gateway ?? 'pesapal',
            'payment_reference' => $order->merchant_reference,
        ]);

        $memorial->update([
            'plan' => 'paid',
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
        ]);
    }
}
