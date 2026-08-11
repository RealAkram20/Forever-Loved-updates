<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * User subscription & billing page.
     * Admin/super-admin are redirected to Payment Orders (they manage orders there).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->hasRole(['admin', 'super-admin'])) {
            return redirect()->route('settings.payment-orders');
        }
        // Host-first: on a reseller's site the catalogue is theirs whoever is looking;
        // the user's own affiliation decides only on the platform's host.
        $plans = SubscriptionPlan::where('is_active', true)->sellableOnHost(\App\Helpers\ThemeSetting::siteTenantId(), $user)->orderBy('sort_order')->get();
        $currentSubscriptions = UserSubscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'overdue'])
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now())
                    ->orWhere('status', 'overdue');
            })
            ->with(['plan', 'memorial'])
            ->latest()
            ->get();
        $currentSubscription = $currentSubscriptions->first();

        $paymentHistory = PaymentOrder::where('user_id', $user->id)
            ->with(['plan', 'memorial'])
            ->latest()
            ->limit(20)
            ->get();

        $paymentsEnabled = (bool) SystemSetting::get('payments.enabled', false);
        $pesapalEnabled = (bool) SystemSetting::get('payments.pesapal_enabled', false);
        $currency = SystemSetting::get('payments.currency', 'USD');

        $checkoutPlanId = $request->query('plan_id') ? (int) $request->query('plan_id') : null;
        $fromSignup = $request->boolean('from_signup');
        $memorialSlug = $request->query('memorial_slug');
        $checkoutPlan = $checkoutPlanId ? $plans->firstWhere('id', $checkoutPlanId) : null;
        $memorials = $user->memorials()->orderBy('full_name')->get(['id', 'slug', 'full_name']);

        return view('pages.subscription.index', [
            'title' => 'My Subscription',
            'plans' => $plans,
            'memorials' => $memorials,
            'currentSubscription' => $currentSubscription,
            'currentSubscriptions' => $currentSubscriptions,
            'paymentHistory' => $paymentHistory,
            'paymentsEnabled' => $paymentsEnabled,
            'pesapalEnabled' => $pesapalEnabled,
            'currency' => $currency,
            'checkoutPlan' => $checkoutPlan,
            'fromSignup' => $fromSignup,
            'memorialSlug' => $memorialSlug,
        ]);
    }
}
