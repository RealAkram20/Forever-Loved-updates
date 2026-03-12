<?php

namespace App\Http\Controllers;

use App\Models\Memorial;
use App\Models\PaymentOrder;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use App\Services\NotificationService;
use App\Services\PesapalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Create a Pesapal order and return redirect_url for checkout.
     */
    public function createOrder(Request $request)
    {
        try {
            return $this->processCreateOrder($request);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Payment failed. Please try again.',
            ], 500);
        }
    }

    private function processCreateOrder(Request $request)
    {
        try {
            $request->validate([
                'plan_id' => ['required', 'exists:subscription_plans,id'],
                'payment_method' => ['nullable', 'string', 'in:mtn,airtel,card'],
                'phone_number' => ['nullable', 'string', 'max:20'],
                'from_signup' => ['nullable', 'boolean'],
                'memorial_slug' => ['nullable', 'string', 'max:255'],
                'memorial_id' => ['nullable', 'integer', 'exists:memorials,id'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            return response()->json(['success' => false, 'error' => $firstError ?? 'Invalid request.'], 422);
        }

        if (! (bool) SystemSetting::get('payments.enabled', false)) {
            return response()->json(['success' => false, 'error' => 'Payments are not enabled.'], 400);
        }

        $pesapal = app(PesapalService::class);
        if (! $pesapal->isEnabled()) {
            return response()->json(['success' => false, 'error' => 'Pesapal is not configured. Check Admin → Settings → Payments.'], 400);
        }

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        if ($plan->isFree()) {
            return response()->json(['success' => false, 'error' => 'Free plans do not require payment.'], 400);
        }

        $user = $request->user();

        $memorial = null;
        $memorialSlug = $request->memorial_slug;
        $memorialId = $request->memorial_id;
        if ($memorialId) {
            $memorial = \App\Models\Memorial::where('id', $memorialId)->where('user_id', $user->id)->first();
        } elseif ($memorialSlug) {
            $memorial = \App\Models\Memorial::where('slug', $memorialSlug)->where('user_id', $user->id)->first();
        }
        if (!$memorial) {
            return response()->json(['success' => false, 'error' => 'Please select a memorial for this subscription.'], 400);
        }
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Please log in to continue.'], 401);
        }

        $currency = SystemSetting::get('payments.currency', 'USD');
        $merchantRef = 'SUB-' . strtoupper(Str::random(8)) . '-' . time();

        $order = PaymentOrder::create([
            'user_id' => $user->id,
            'memorial_id' => $memorial->id,
            'subscription_plan_id' => $plan->id,
            'merchant_reference' => $merchantRef,
            'amount' => $plan->price,
            'currency' => $currency,
            'status' => 'pending',
            'payment_gateway' => 'pesapal',
            'payment_method' => $request->payment_method,
            'metadata' => array_filter([
                'from_signup' => $request->from_signup,
                'memorial_slug' => $memorial->slug,
            ]),
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

        $callbackUrl = $pesapal->getCallbackUrl('payment.callback');
        $cancellationUrl = $pesapal->getCallbackUrl('payment.complete', ['result' => 'cancelled']);

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
            $errorMsg = $result['error'] ?? 'Payment initiation failed';
            if (str_contains(strtolower($errorMsg), 'ipn')) {
                $errorMsg = 'IPN not configured. Register your IPN URL in Pesapal dashboard, then add the IPN ID in Admin → Settings → Payments.';
            }
            if (str_contains(strtolower($errorMsg), 'auth')) {
                $errorMsg = 'Pesapal authentication failed. Check your Consumer Key and Secret in Admin → Settings → Payments.';
            }
            return response()->json(['success' => false, 'error' => $errorMsg], 400);
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
            return redirect()->route('payment.complete', ['result' => 'error', 'message' => 'Invalid callback parameters.']);
        }

        $order = PaymentOrder::where('merchant_reference', $merchantRef)->first();
        if (! $order) {
            return redirect()->route('payment.complete', ['result' => 'error', 'message' => 'Order not found.']);
        }
        // IPN may have already processed the payment before the callback - show success
        if ($order->isCompleted()) {
            return $this->redirectToCompletionPage('success', 'Payment successful! Your subscription is now active.', $order);
        }
        if (! $order->isPending()) {
            return redirect()->route('payment.complete', ['result' => 'error', 'message' => 'Order already processed.']);
        }

        $pesapal = app(PesapalService::class);
        $status = null;
        foreach ([0, 1, 2, 3] as $attempt) {
            if ($attempt > 0) {
                usleep(500000 * $attempt); // 0.5s, 1s, 1.5s delay before retry
            }
            $status = $pesapal->getTransactionStatus($orderTrackingId);
            if ($status && $pesapal->isPaymentCompleted($status)) {
                break;
            }
            if ($status && $pesapal->isPaymentFailed($status)) {
                // Pesapal may return transient "failed" before updating to completed - retry once more
                if ($attempt >= 2) {
                    break;
                }
                usleep(1500000); // 1.5s extra wait before re-checking "failed"
            }
        }

        if (! $status) {
            return redirect()->route('payment.complete', ['result' => 'info', 'message' => 'Could not verify payment status. The order will update when Pesapal sends the IPN notification.']);
        }

        return $this->processPaymentResult($order, $status, 'callback');
    }

    /**
     * Public payment completion page - no auth required.
     * Used when Pesapal redirects back (iframe may not have session cookies).
     */
    public function complete(Request $request)
    {
        $result = $request->query('result');
        $message = $request->query('message');
        $token = $request->query('token');

        $data = ['result' => 'info', 'message' => 'Payment processing.', 'redirect_url' => null, 'redirect_label' => 'Back to Subscription'];

        if ($token) {
            $cached = Cache::pull('payment_complete_' . $token);
            if ($cached && is_array($cached)) {
                $data = array_merge($data, $cached);
            }
        } elseif ($result) {
            $data['result'] = in_array($result, ['success', 'error', 'info', 'cancelled']) ? $result : 'info';
            $data['message'] = $message ?? match ($data['result']) {
                'cancelled' => 'Payment was cancelled.',
                'error' => 'Something went wrong.',
                'success' => 'Payment successful!',
                default => 'Payment is being processed.',
            };
            if ($data['result'] === 'cancelled') {
                $data['redirect_url'] = url('/');
                $data['redirect_label'] = 'Back to Home';
            }
        }

        if (! $data['redirect_url']) {
            $data['redirect_url'] = route('subscription.index');
            $data['redirect_label'] = 'Back to Subscription';
        }

        return view('pages.payment.complete', $data);
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
        $status = null;
        foreach ([0, 1, 2, 3] as $attempt) {
            if ($attempt > 0) {
                usleep(300000 * $attempt); // 0.3s, 0.6s, 0.9s delay before retry
            }
            $status = $pesapal->getTransactionStatus($orderTrackingId);
            if ($status && ($pesapal->isPaymentCompleted($status) || $pesapal->isPaymentFailed($status))) {
                break;
            }
        }

        if (! $status) {
            Log::warning('Pesapal IPN: getTransactionStatus returned null', ['orderTrackingId' => $orderTrackingId, 'merchantRef' => $merchantRef]);
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
            $memorial = $order->memorial;
            if (!$memorial || ($memorial->status ?? Memorial::STATUS_ACTIVE) !== Memorial::STATUS_ACTIVE) {
                $order->update(['status' => 'cancelled']);
                if ($source === 'callback') {
                    return redirect()->route('payment.complete', ['result' => 'error', 'message' => 'Payment cancelled: memorial no longer available.']);
                }
                return response()->json(['status' => 200], 200);
            }

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
                return $this->redirectToCompletionPage('success', 'Payment successful! Your subscription is now active.', $order);
            }
        } elseif ($pesapal->isPaymentFailed($status)) {
            $order->update(['status' => 'failed']);
            if ($source === 'callback') {
                return $this->redirectToCompletionPage('error', 'Payment failed. Please try again.', $order);
            }
        }

        if ($source === 'callback') {
            return $this->redirectToCompletionPage('info', 'Payment is being processed. We will notify you when it is complete.', $order);
        }

        return response()->json(['status' => 200], 200);
    }

    /**
     * Redirect to public completion page with one-time token (avoids auth issues in iframe).
     */
    private function redirectToCompletionPage(string $result, string $message, PaymentOrder $order): \Illuminate\Http\RedirectResponse
    {
        $memorialSlug = $order->metadata['memorial_slug'] ?? null;
        $isAdminCreated = ($order->metadata['admin_created'] ?? false) === true;

        $redirectUrl = route('subscription.index');
        $redirectLabel = 'Back to Subscription';

        if ($memorialSlug) {
            $redirectUrl = route('memorial.create.preparing', ['slug' => $memorialSlug]);
            $redirectLabel = 'Continue to Memorial';
        } elseif ($isAdminCreated) {
            $redirectUrl = route('settings.payment-orders');
            $redirectLabel = 'Back to Payment Orders';
        }

        $token = Str::random(48);
        Cache::put('payment_complete_' . $token, [
            'result' => $result,
            'message' => $message,
            'redirect_url' => $redirectUrl,
            'redirect_label' => $redirectLabel,
        ], now()->addMinutes(15));

        return redirect()->route('payment.complete', ['token' => $token]);
    }

    private function activateSubscription(PaymentOrder $order): void
    {
        $plan = $order->plan;
        $user = $order->user;
        $memorial = $order->memorial;
        if (!$plan || !$user || !$memorial) {
            return;
        }

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
            'payment_gateway' => 'pesapal',
            'payment_reference' => $order->merchant_reference,
        ]);

        $memorial->update([
            'plan' => 'paid',
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
        ]);
    }
}
