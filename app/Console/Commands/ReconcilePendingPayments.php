<?php

namespace App\Console\Commands;

use App\Models\PaymentOrder;
use App\Services\NotificationService;
use App\Services\PaymentResultProcessor;
use App\Services\PesapalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Self-heals orders stuck in `pending` when both the payment callback and the
 * IPN failed to land (user closed the tab, Pesapal outage, blocked webhook).
 * Runs from the scheduler — deliberately a command rather than a queued job so
 * payment healing never depends on queue health.
 */
class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=25 : Max orders to verify with Pesapal per run}';

    protected $description = 'Re-check pending Pesapal orders against the gateway and expire abandoned ones';

    /** Skip orders younger than this — the customer may still be mid-checkout. */
    private const MIN_AGE_MINUTES = 10;

    /** Orders pending longer than this are considered abandoned. */
    private const MAX_AGE_HOURS = 48;

    public function handle(PaymentResultProcessor $processor): int
    {
        $expired = $this->expireAbandonedOrders();

        $orders = PaymentOrder::where('status', 'pending')
            ->where('payment_gateway', 'pesapal')
            ->whereNotNull('order_tracking_id')
            ->whereBetween('created_at', [now()->subHours(self::MAX_AGE_HOURS), now()->subMinutes(self::MIN_AGE_MINUTES)])
            ->orderBy('created_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $completed = $failed = $unreachable = 0;

        foreach ($orders as $order) {
            // Each order re-verifies against whichever gateway it was actually paid
            // through — the platform's own credentials, or the owning reseller's.
            $pesapal = PesapalService::forReseller($order->reseller);
            if (! $pesapal->isEnabled()) {
                continue;
            }

            try {
                $status = $pesapal->getTransactionStatus($order->order_tracking_id);
                if ($status === null) {
                    $unreachable++;
                    continue; // gateway unreachable or unknown — retry next run
                }

                $outcome = $processor->process($order, $status, 'reconciliation');

                if ($outcome === PaymentResultProcessor::OUTCOME_COMPLETED) {
                    $completed++;
                    Log::info('Payment reconciled to completed', ['order_id' => $order->id, 'merchant_reference' => $order->merchant_reference]);
                    $this->notifyCompleted($order);
                } elseif ($outcome === PaymentResultProcessor::OUTCOME_FAILED) {
                    $failed++;
                }
            } catch (\Throwable $e) {
                Log::warning('Payment reconciliation failed for order', [
                    'order_id' => $order->id,
                    'merchant_reference' => $order->merchant_reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Checked {$orders->count()} pending order(s): {$completed} completed, {$failed} failed, {$unreachable} unreachable, {$expired} expired.");

        return self::SUCCESS;
    }

    /**
     * Pesapal orders pending past the abandonment window can never complete on
     * their own; cancel them so they stop being rechecked and the customer can
     * retry cleanly. Manual-gateway orders are an admin workflow — untouched.
     */
    private function expireAbandonedOrders(): int
    {
        $expired = 0;

        PaymentOrder::where('status', 'pending')
            ->where('payment_gateway', 'pesapal')
            ->where('created_at', '<', now()->subHours(self::MAX_AGE_HOURS))
            ->get()
            ->each(function (PaymentOrder $order) use (&$expired) {
                $order->update([
                    'status' => 'cancelled',
                    'metadata' => array_merge($order->metadata ?? [], ['cancel_reason' => 'expired']),
                ]);
                $expired++;
            });

        return $expired;
    }

    private function notifyCompleted(PaymentOrder $order): void
    {
        try {
            $plan = $order->plan;
            if ($plan && $order->user) {
                NotificationService::notifyPaymentMade(
                    $order->user,
                    $plan->name,
                    number_format($order->amount, 2).' '.$order->currency
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Reconciliation completed but notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
