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
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        $currentSubscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->with('plan')
            ->latest()
            ->first();

        $paymentHistory = PaymentOrder::where('user_id', $user->id)
            ->with('plan')
            ->latest()
            ->limit(20)
            ->get();

        $paymentsEnabled = (bool) SystemSetting::get('payments.enabled', false);
        $pesapalEnabled = (bool) SystemSetting::get('payments.pesapal_enabled', false);

        $checkoutPlanId = $request->query('plan_id') ? (int) $request->query('plan_id') : null;
        $fromSignup = $request->boolean('from_signup');
        $memorialSlug = $request->query('memorial_slug');
        $checkoutPlan = $checkoutPlanId ? $plans->firstWhere('id', $checkoutPlanId) : null;

        return view('pages.subscription.index', [
            'title' => 'My Subscription',
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
            'paymentHistory' => $paymentHistory,
            'paymentsEnabled' => $paymentsEnabled,
            'pesapalEnabled' => $pesapalEnabled,
            'checkoutPlan' => $checkoutPlan,
            'fromSignup' => $fromSignup,
            'memorialSlug' => $memorialSlug,
        ]);
    }
}
