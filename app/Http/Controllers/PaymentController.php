<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use App\Services\NotificationService;
use App\Services\PesapalService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Create a Pesapal order and return redirect_url for checkout.
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'payment_method' => ['nullable', 'string', 'in:mtn,airtel,card'],
            'from_signup' => ['nullable', 'boolean'],
            'memorial_slug' => ['nullable', 'string', 'max:255'],
        ]);

        if (! (bool) SystemSetting::get('payments.enabled', false)) {
            return response()->json(['success' => false, 'error' => 'Payments are not enabled.'], 400);
        }

        $pesapal = app(PesapalService::class);
        if (! $pesapal->isEnabled()) {
            return response()->json(['success' => false, 'error' => 'Pesapal is not configured.'], 400);
        }

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        if ($plan->isFree()) {
            return response()->json(['success' => false, 'error' => 'Free plans do not require payment.'], 400);
        }

        $user = $request->user();
        $currency = SystemSetting::get('payments.currency', 'USD');
        $merchantRef = 'SUB-' . strtoupper(Str::random(8)) . '-' . time();

        $order = PaymentOrder::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'merchant_reference' => $merchantRef,
            'amount' => $plan->price,
            'currency' => $currency,
            'status' => 'pending',
            'payment_gateway' => 'pesapal',
            'payment_method' => $request->payment_method,
            'metadata' => $request->only(['from_signup', 'memorial_slug']),
        ]);

        $billingAddress = [
            'email_address' => $user->email,
            'first_name' => explode(' ', $user->name)[0] ?? $user->name,
            'last_name' => explode(' ', $user->name)[1] ?? '',
            'country_code' => 'KE',
        ];

        if (in_array($request->payment_method, ['mtn', 'airtel'])) {
            $billingAddress['phone_number'] = $request->input('phone_number', '');
        }

        $callbackUrl = route('payment.callback');
        $cancellationUrl = route('subscription.index');

        $result = $pesapal->submitOrder(
            $merchantRef,
            (float) $plan->price,
            $currency,
            "Subscription: {$plan->name} ({$plan->interval})",
            $callbackUrl,
            $billingAddress,
            $cancellationUrl
        );

        if (! $result['success']) {
            $order->update(['status' => 'failed']);
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Payment initiation failed'], 400);
        }

        $order->update(['order_tracking_id' => $result['order_tracking_id'] ?? null]);

        return response()->json([
            'success' => true,
            'redirect_url' => $result['redirect_url'],
            'merchant_reference' => $merchantRef,
        ]);
    }

    /**
     * Callback URL - Pesapal redirects user here after payment.
     */
    public function callback(Request $request)
    {
        $orderTrackingId = $request->query('OrderTrackingId');
        $merchantRef = $request->query('OrderMerchantReference');

        if (! $orderTrackingId || ! $merchantRef) {
            return redirect()->route('subscription.index')->with('error', 'Invalid callback parameters.');
        }

        $order = PaymentOrder::where('merchant_reference', $merchantRef)->first();
        if (! $order || ! $order->isPending()) {
            return redirect()->route('subscription.index')->with('error', 'Order not found or already processed.');
        }

        $pesapal = app(PesapalService::class);
        $status = $pesapal->getTransactionStatus($orderTrackingId);

        if (! $status) {
            return redirect()->route('subscription.index')->with('error', 'Could not verify payment status.');
        }

        return $this->processPaymentResult($order, $status, 'callback');
    }

    /**
     * IPN endpoint - Pesapal sends server-to-server notification.
     */
    public function ipn(Request $request)
    {
        $orderTrackingId = $request->input('OrderTrackingId') ?? $request->query('OrderTrackingId');
        $merchantRef = $request->input('OrderMerchantReference') ?? $request->query('OrderMerchantReference');

        if (! $orderTrackingId || ! $merchantRef) {
            return response()->json(['orderNotificationType' => 'IPNCHANGE', 'status' => 500], 200);
        }

        $order = PaymentOrder::where('merchant_reference', $merchantRef)->first();
        if (! $order) {
            return response()->json(['orderNotificationType' => 'IPNCHANGE', 'status' => 500], 200);
        }

        if ($order->isCompleted()) {
            return response()->json([
                'orderNotificationType' => 'IPNCHANGE',
                'orderTrackingId' => $orderTrackingId,
                'orderMerchantReference' => $merchantRef,
                'status' => 200,
            ], 200);
        }

        $pesapal = app(PesapalService::class);
        $status = $pesapal->getTransactionStatus($orderTrackingId);

        if (! $status) {
            return response()->json(['orderNotificationType' => 'IPNCHANGE', 'status' => 500], 200);
        }

        $this->processPaymentResult($order, $status, 'ipn');

        return response()->json([
            'orderNotificationType' => 'IPNCHANGE',
            'orderTrackingId' => $orderTrackingId,
            'orderMerchantReference' => $merchantRef,
            'status' => 200,
        ], 200);
    }

    private function processPaymentResult(PaymentOrder $order, array $status, string $source): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $pesapal = app(PesapalService::class);

        if ($pesapal->isPaymentCompleted($status)) {
            $order->update([
                'status' => 'completed',
                'confirmation_code' => $status['confirmation_code'] ?? null,
            ]);

            $this->activateSubscription($order);

            if ($source === 'callback') {
                $plan = $order->plan;
                NotificationService::notifyPaymentMade(
                    $order->user,
                    $plan->name,
                    number_format($order->amount, 2) . ' ' . $order->currency
                );
                $memorialSlug = $order->metadata['memorial_slug'] ?? null;
                if ($memorialSlug) {
                    return redirect()->route('memorial.create.preparing', ['slug' => $memorialSlug])
                        ->with('success', 'Payment successful! Your subscription is now active.');
                }
                return redirect()->route('subscription.index')->with('success', 'Payment successful! Your subscription is now active.');
            }
        } elseif ($pesapal->isPaymentFailed($status)) {
            $order->update(['status' => 'failed']);
            if ($source === 'callback') {
                return redirect()->route('subscription.index')->with('error', 'Payment failed. Please try again.');
            }
        }

        if ($source === 'callback') {
            return redirect()->route('subscription.index')->with('info', 'Payment is being processed. We will notify you when it is complete.');
        }

        return response()->json(['status' => 200], 200);
    }

    private function activateSubscription(PaymentOrder $order): void
    {
        $plan = $order->plan;
        $user = $order->user;

        $startsAt = now();
        $endsAt = match ($plan->interval) {
            'monthly' => $startsAt->copy()->addMonth(),
            'yearly' => $startsAt->copy()->addYear(),
            default => null,
        };

        UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'active',
            'payment_gateway' => 'pesapal',
            'payment_reference' => $order->merchant_reference,
        ]);
    }
}
