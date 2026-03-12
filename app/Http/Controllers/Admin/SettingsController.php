<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Memorial;
use App\Models\PaymentOrder;
use App\Models\SystemSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function __construct()
    {
        // Admin/super-admin check handled in routes middleware
    }

    // ─── General / Branding ──────────────────────────────────────────

    public function general()
    {
        $settings = SystemSetting::getByGroup('branding');

        return view('pages.settings.general', [
            'title' => 'General Settings',
            'settings' => $settings,
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $request->validate([
            'branding.app_name' => 'required|string|max:100',
            'branding.tagline' => 'nullable|string|max:255',
            'branding.primary_color' => 'required|string|max:20',
            'branding.secondary_color' => 'required|string|max:20',
            'branding.accent_color' => 'required|string|max:20',
            'logo' => 'nullable|image|max:2048',
            'logo_dark' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:512',
        ]);

        foreach (['branding.app_name', 'branding.tagline', 'branding.primary_color', 'branding.secondary_color', 'branding.accent_color'] as $key) {
            if ($request->has($key)) {
                SystemSetting::set($key, $request->input($key));
            }
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');
            SystemSetting::set('branding.logo_path', $path);
        }

        if ($request->hasFile('logo_dark')) {
            $path = $request->file('logo_dark')->store('branding', 'public');
            SystemSetting::set('branding.logo_dark_path', $path);
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('branding', 'public');
            SystemSetting::set('branding.favicon_path', $path);
        }

        return back()->with('success', 'General settings updated successfully.');
    }

    // ─── AI Configuration ────────────────────────────────────────────

    public function ai()
    {
        $settings = SystemSetting::getByGroup('ai');

        return view('pages.settings.ai', [
            'title' => 'AI Configuration',
            'settings' => $settings,
        ]);
    }

    public function updateAi(Request $request)
    {
        $request->validate([
            'ai.enabled' => 'required|in:0,1',
            'ai.provider' => 'required|string|in:openai,anthropic,gemini',
            'ai.api_key' => 'nullable|string|max:255',
            'ai.model' => 'required|string|max:100',
            'ai.max_requests_per_user_per_day' => 'required|integer|min:0|max:1000',
            'ai.max_requests_per_user_per_month' => 'required|integer|min:0|max:10000',
            'ai.max_tokens_per_request' => 'required|integer|min:100|max:32000',
        ]);

        $keys = [
            'ai.enabled', 'ai.provider', 'ai.model',
            'ai.max_requests_per_user_per_day',
            'ai.max_requests_per_user_per_month',
            'ai.max_tokens_per_request',
        ];

        foreach ($keys as $key) {
            SystemSetting::set($key, $request->input($key));
        }

        $apiKey = $request->input('ai.api_key');
        if ($apiKey && $apiKey !== '••••••••') {
            SystemSetting::set('ai.api_key', $apiKey);
        }

        return back()->with('success', 'AI settings updated successfully.');
    }

    // ─── Permissions ─────────────────────────────────────────────────

    public function permissions()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();
        $users = User::with('roles')->orderBy('name')->get();

        return view('pages.settings.permissions', [
            'title' => 'Permissions',
            'roles' => $roles,
            'permissions' => $permissions,
            'users' => $users,
        ]);
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
        ]);

        Role::create(['name' => $request->name, 'guard_name' => 'web']);

        return back()->with('success', 'Role created successfully.');
    }

    public function updateUserRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->syncRoles([$request->role]);

        return back()->with('success', "Role updated for {$user->name}.");
    }

    public function destroyRole(Role $role)
    {
        if (in_array($role->name, ['super-admin', 'admin', 'user'])) {
            return back()->with('error', 'Cannot delete system roles.');
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }

    // ─── Payments ────────────────────────────────────────────────────

    public function payments()
    {
        $settings = SystemSetting::getByGroup('payments');

        return view('pages.settings.payments', [
            'title' => 'Payment Settings',
            'settings' => $settings,
        ]);
    }

    public function updatePayments(Request $request)
    {
        $request->validate([
            'payments.enabled' => 'required|in:0,1',
            'payments.currency' => 'required|string|max:10',
            'payments.stripe_enabled' => 'required|in:0,1',
            'payments.stripe_public_key' => 'nullable|string|max:255',
            'payments.stripe_secret_key' => 'nullable|string|max:255',
            'payments.pesapal_enabled' => 'required|in:0,1',
            'payments.pesapal_consumer_key' => 'nullable|string|max:255',
            'payments.pesapal_consumer_secret' => 'nullable|string|max:255',
            'payments.pesapal_environment' => 'required|in:sandbox,live',
            'payments.pesapal_ipn_id' => 'nullable|string|max:255',
        ]);

        $keys = [
            'payments.enabled', 'payments.currency',
            'payments.stripe_enabled', 'payments.stripe_public_key',
            'payments.pesapal_enabled', 'payments.pesapal_consumer_key',
            'payments.pesapal_environment', 'payments.pesapal_ipn_id',
        ];

        foreach ($keys as $key) {
            SystemSetting::set($key, $request->input($key));
        }

        $stripeSecret = $request->input('payments.stripe_secret_key');
        if ($stripeSecret && $stripeSecret !== '••••••••') {
            SystemSetting::set('payments.stripe_secret_key', $stripeSecret);
        }

        $pesapalSecret = $request->input('payments.pesapal_consumer_secret');
        if ($pesapalSecret && $pesapalSecret !== '••••••••') {
            SystemSetting::set('payments.pesapal_consumer_secret', $pesapalSecret);
        }

        return back()->with('success', 'Payment settings updated successfully.');
    }

    // ─── Payment Orders (transactions) ──────────────────────────────────

    public function paymentOrders(Request $request)
    {
        $query = PaymentOrder::with(['user', 'plan', 'memorial'])->orderByDesc('created_at');

        $status = $request->query('status');
        if ($status && in_array($status, ['pending', 'completed', 'failed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(25)->withQueryString();

        $adminId = $request->user()->id;
        $users = User::where('id', '!=', $adminId)->orderBy('name')->get(['id', 'name', 'email']);
        $plans = SubscriptionPlan::orderBy('sort_order')->get();
        $memorials = Memorial::with('owner')->orderBy('full_name')->get(['id', 'slug', 'full_name', 'user_id']);

        $currency = SystemSetting::get('payments.currency', 'USD');

        return view('pages.settings.payment-orders', [
            'title' => 'Payment Orders',
            'orders' => $orders,
            'users' => $users,
            'plans' => $plans,
            'memorials' => $memorials,
            'currency' => $currency,
        ]);
    }

    public function storePaymentOrder(Request $request)
    {
        $admin = $request->user();
        $gateway = $request->input('payment_gateway', 'manual');

        $rules = [
            'user_id' => ['required', 'exists:users,id', 'different:' . $admin->id],
            'memorial_id' => ['required', 'exists:memorials,id'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'payment_gateway' => ['required', 'string', 'in:manual,pesapal'],
        ];
        if ($gateway === 'manual') {
            $rules['status'] = ['required', 'in:pending,completed,failed,cancelled'];
        }
        $request->validate($rules);

        $memorial = Memorial::findOrFail($request->memorial_id);
        if ($memorial->user_id != $request->user_id) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Memorial must belong to the selected user.'], 422);
            }
            return back()->with('error', 'Memorial must belong to the selected user.');
        }

        $plan = SubscriptionPlan::findOrFail($request->subscription_plan_id);
        if ($plan->isFree() && $gateway === 'pesapal') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Free plans do not require Pesapal payment.'], 422);
            }
            return back()->with('error', 'Free plans do not require Pesapal payment.');
        }

        $currency = SystemSetting::get('payments.currency', 'USD');
        $status = $gateway === 'pesapal' ? 'pending' : $request->status;

        $merchantRef = 'ADM-' . strtoupper(\Illuminate\Support\Str::random(8)) . '-' . time();
        $order = PaymentOrder::create([
            'user_id' => $request->user_id,
            'memorial_id' => $request->memorial_id,
            'subscription_plan_id' => $request->subscription_plan_id,
            'merchant_reference' => $merchantRef,
            'amount' => $plan->price,
            'currency' => $currency,
            'status' => $status,
            'payment_gateway' => $gateway,
            'metadata' => ['admin_created' => true],
        ]);

        if ($gateway === 'manual' && $status === 'completed') {
            $this->activateSubscriptionForOrder($order);
        }

        if ($gateway === 'pesapal') {
            $pesapal = app(\App\Services\PesapalService::class);
            if (! $pesapal->isEnabled()) {
                $order->update(['status' => 'failed']);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'error' => 'Pesapal is not configured. Check Settings → Payments.'], 400);
                }
                return back()->with('error', 'Pesapal is not configured.');
            }

            $user = $order->user;
            $billingAddress = [
                'email_address' => $user->email ?? '',
                'first_name' => explode(' ', $user->name ?? '')[0] ?? 'User',
                'last_name' => explode(' ', $user->name ?? '')[1] ?? '',
                'country_code' => 'KE',
            ];

            $pesapal = app(\App\Services\PesapalService::class);
            $callbackUrl = $pesapal->getCallbackUrl('payment.callback');
            $cancellationUrl = $pesapal->getCallbackUrl('settings.payment-orders');

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
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'error' => $errorMsg], 400);
                }
                return back()->with('error', $errorMsg);
            }

            $order->update(['order_tracking_id' => $result['order_tracking_id'] ?? null]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $result['redirect_url'],
                    'message' => 'Payment order created. Complete payment in the popup.',
                ]);
            }
            return back()->with('info', 'Order created. Payment URL: ' . ($result['redirect_url'] ?? ''));
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Payment order created.', 'reload' => true]);
        }
        return back()->with('success', 'Payment order created.');
    }

    public function updatePaymentOrder(Request $request, PaymentOrder $order)
    {
        $admin = $request->user();
        $request->validate([
            'user_id' => ['required', 'exists:users,id', 'different:' . $admin->id],
            'memorial_id' => ['required', 'exists:memorials,id'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'status' => ['required', 'in:pending,completed,failed,cancelled'],
        ]);

        $memorial = Memorial::findOrFail($request->memorial_id);
        if ($memorial->user_id != $request->user_id) {
            return back()->with('error', 'Memorial must belong to the selected user.');
        }

        $order->update([
            'user_id' => $request->user_id,
            'memorial_id' => $request->memorial_id,
            'subscription_plan_id' => $request->subscription_plan_id,
            'status' => $request->status,
        ]);

        if ($request->status === 'completed' && $order->wasChanged('status')) {
            $this->activateSubscriptionForOrder($order);
        }

        return back()->with('success', 'Payment order updated.');
    }

    public function destroyPaymentOrder(Request $request, PaymentOrder $order)
    {
        $order->delete();
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Payment order deleted.']);
        }
        return back()->with('success', 'Payment order deleted.');
    }

    public function bulkPaymentOrders(Request $request)
    {
        $request->validate([
            'action' => 'required|in:approve,delete,mark_failed',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:payment_orders,id',
        ]);

        $ids = $request->input('ids', []);
        $action = $request->input('action');
        $orders = PaymentOrder::whereIn('id', $ids)->with(['user', 'plan'])->get();

        $count = 0;
        foreach ($orders as $order) {
            if ($action === 'approve') {
                if ($order->status !== 'completed') {
                    $order->update(['status' => 'completed']);
                    $this->activateSubscriptionForOrder($order);
                    $count++;
                }
            } elseif ($action === 'mark_failed') {
                $order->update(['status' => 'failed']);
                $count++;
            } elseif ($action === 'delete') {
                $order->delete();
                $count++;
            }
        }

        $message = match ($action) {
            'approve' => $count . ' payment(s) approved and subscription(s) activated.',
            'mark_failed' => $count . ' payment(s) marked as failed.',
            'delete' => $count . ' payment(s) deleted.',
            default => 'Done.',
        };

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    private function activateSubscriptionForOrder(PaymentOrder $order): void
    {
        $plan = $order->plan;
        $user = $order->user;
        $memorial = $order->memorial;
        if (!$plan || !$user || !$memorial) {
            return;
        }

        $hasActive = UserSubscription::where('memorial_id', $memorial->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();

        if ($hasActive) {
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
            'payment_gateway' => $order->payment_gateway ?? 'pesapal',
            'payment_reference' => $order->merchant_reference,
        ]);

        $memorial->update([
            'plan' => 'paid',
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
        ]);
    }

    // ─── SMTP / Email ───────────────────────────────────────────────

    public function smtp()
    {
        $settings = SystemSetting::getByGroup('smtp');

        return view('pages.settings.smtp', [
            'title' => 'SMTP Configuration',
            'settings' => $settings,
        ]);
    }

    public function updateSmtp(Request $request)
    {
        $request->validate([
            'smtp.enabled' => 'required|in:0,1',
            'smtp.host' => 'nullable|string|max:255',
            'smtp.port' => 'required|integer|min:1|max:65535',
            'smtp.username' => 'nullable|string|max:255',
            'smtp.password' => 'nullable|string|max:255',
            'smtp.encryption' => 'required|in:tls,ssl,none',
            'smtp.from_address' => 'nullable|email|max:255',
            'smtp.from_name' => 'nullable|string|max:255',
        ]);

        $keys = [
            'smtp.enabled', 'smtp.host', 'smtp.port',
            'smtp.username', 'smtp.encryption',
            'smtp.from_address', 'smtp.from_name',
        ];

        foreach ($keys as $key) {
            SystemSetting::set($key, $request->input($key));
        }

        $password = $request->input('smtp.password');
        if ($password && $password !== '••••••••') {
            SystemSetting::set('smtp.password', $password);
        }

        return back()->with('success', 'SMTP settings updated successfully.');
    }

    // ─── Notification Settings ──────────────────────────────────────

    public function notifications()
    {
        $settings = SystemSetting::getByGroup('notifications');

        return view('pages.settings.notifications', [
            'title' => 'Notification Settings',
            'settings' => $settings,
        ]);
    }

    public function updateNotifications(Request $request)
    {
        $request->validate([
            'notifications.email_enabled' => 'required|in:0,1',
            'notifications.push_enabled' => 'required|in:0,1',
            'notifications.vapid_public_key' => 'nullable|string|max:500',
            'notifications.vapid_private_key' => 'nullable|string|max:500',
        ]);

        $keys = [
            'notifications.email_enabled',
            'notifications.push_enabled',
            'notifications.vapid_public_key',
        ];

        foreach ($keys as $key) {
            SystemSetting::set($key, $request->input($key));
        }

        $vapidPrivate = $request->input('notifications.vapid_private_key');
        if ($vapidPrivate && $vapidPrivate !== '••••••••') {
            SystemSetting::set('notifications.vapid_private_key', $vapidPrivate);
        }

        return back()->with('success', 'Notification settings updated successfully.');
    }

    // ─── Subscriptions ───────────────────────────────────────────────

    public function subscriptions(Request $request)
    {
        $query = UserSubscription::with(['user', 'plan'])->orderByDesc('created_at');

        $status = $request->query('status');
        if ($status && in_array($status, ['active', 'cancelled', 'expired', 'paused', 'pending'], true)) {
            $query->where('status', $status);
        }

        $subscriptions = $query->with('memorial')->paginate(20)->withQueryString();

        return view('pages.settings.subscriptions', [
            'title' => 'Subscriptions',
            'subscriptions' => $subscriptions,
            'plans' => SubscriptionPlan::orderBy('sort_order')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'memorials' => Memorial::with('owner')->orderBy('full_name')->get(['id', 'slug', 'full_name', 'user_id']),
        ]);
    }

    public function storeSubscription(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'memorial_id' => 'required|exists:memorials,id',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'status' => 'required|in:active,cancelled,expired,paused,pending',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'payment_gateway' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $memorial = Memorial::findOrFail($request->memorial_id);
        if ($memorial->user_id != $request->user_id) {
            return back()->with('error', 'Memorial must belong to the selected user.');
        }

        $subscription = UserSubscription::create([
            'user_id' => $request->user_id,
            'memorial_id' => $request->memorial_id,
            'subscription_plan_id' => $request->subscription_plan_id,
            'status' => $request->status,
            'starts_at' => $request->starts_at,
            'ends_at' => $request->ends_at ?: null,
            'payment_gateway' => $request->payment_gateway ?: null,
            'payment_reference' => $request->payment_reference ?: null,
        ]);

        $plan = SubscriptionPlan::find($request->subscription_plan_id);
        $memorial->update([
            'plan' => $request->status === 'active' ? ($plan && $plan->isFree() ? 'free' : 'paid') : $memorial->plan,
            'subscription_plan_id' => $subscription->subscription_plan_id,
            'user_subscription_id' => $subscription->id,
        ]);

        return back()->with('success', 'Subscription created successfully.');
    }

    public function updateSubscription(Request $request, UserSubscription $subscription)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'memorial_id' => 'required|exists:memorials,id',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'status' => 'required|in:active,cancelled,expired,paused,pending',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'payment_gateway' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $memorial = Memorial::findOrFail($request->memorial_id);
        if ($memorial->user_id != $request->user_id) {
            return back()->with('error', 'Memorial must belong to the selected user.');
        }

        $previousStatus = $subscription->status;
        $oldMemorialId = $subscription->memorial_id;
        $subscription->update([
            'user_id' => $request->user_id,
            'memorial_id' => $request->memorial_id,
            'subscription_plan_id' => $request->subscription_plan_id,
            'status' => $request->status,
            'starts_at' => $request->starts_at,
            'ends_at' => $request->ends_at ?: null,
            'payment_gateway' => $request->payment_gateway ?: null,
            'payment_reference' => $request->payment_reference ?: null,
        ]);

        $plan = SubscriptionPlan::find($request->subscription_plan_id);
        $memorial->update([
            'plan' => $request->status === 'active' ? ($plan && $plan->isFree() ? 'free' : 'paid') : ($memorial->user_subscription_id == $subscription->id ? 'free' : $memorial->plan),
            'subscription_plan_id' => $subscription->subscription_plan_id,
            'user_subscription_id' => $subscription->id,
        ]);

        if ($oldMemorialId && $oldMemorialId != $request->memorial_id) {
            $oldMemorial = Memorial::find($oldMemorialId);
            if ($oldMemorial && $oldMemorial->user_subscription_id == $subscription->id) {
                $oldMemorial->update(['subscription_plan_id' => null, 'user_subscription_id' => null, 'plan' => 'free']);
            }
        }

        if ($request->status === 'cancelled' && $previousStatus !== 'cancelled') {
            $user = $subscription->user;
            $planName = $subscription->plan?->name ?? 'subscription';
            if ($user) {
                NotificationService::notifyPaymentCanceled($user, $planName);
            }
        }

        return back()->with('success', 'Subscription updated.');
    }

    // ─── Plans ───────────────────────────────────────────────────────

    public function plans()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();
        $currency = SystemSetting::get('payments.currency', 'USD');

        return view('pages.settings.plans', [
            'title' => 'Subscription Plans',
            'plans' => $plans,
            'currency' => $currency,
        ]);
    }

    public function storePlan(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50|unique:subscription_plans,slug',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly,lifetime',
            'memorial_limit' => 'required|integer|min:1',
            'storage_limit_mb' => 'required|integer|min:10',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        SubscriptionPlan::create($request->only([
            'name', 'slug', 'description', 'price', 'interval',
            'memorial_limit', 'storage_limit_mb', 'is_active', 'sort_order',
        ]));

        return back()->with('success', 'Plan created successfully.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly,lifetime',
            'memorial_limit' => 'required|integer|min:1',
            'storage_limit_mb' => 'required|integer|min:10',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $plan->update($request->only([
            'name', 'description', 'price', 'interval',
            'memorial_limit', 'storage_limit_mb', 'is_active', 'sort_order',
        ]));

        return back()->with('success', 'Plan updated successfully.');
    }

    public function destroyPlan(SubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'Cannot delete a plan with active subscriptions.');
        }

        $plan->delete();

        return back()->with('success', 'Plan deleted successfully.');
    }
}
