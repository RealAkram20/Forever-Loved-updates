<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function subscriptions()
    {
        $subscriptions = UserSubscription::with(['user', 'plan'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('pages.settings.subscriptions', [
            'title' => 'Subscriptions',
            'subscriptions' => $subscriptions,
        ]);
    }

    public function updateSubscription(Request $request, UserSubscription $subscription)
    {
        $request->validate([
            'status' => 'required|in:active,cancelled,expired,paused',
        ]);

        $previousStatus = $subscription->status;
        $subscription->update(['status' => $request->status]);

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

        return view('pages.settings.plans', [
            'title' => 'Subscription Plans',
            'plans' => $plans,
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
