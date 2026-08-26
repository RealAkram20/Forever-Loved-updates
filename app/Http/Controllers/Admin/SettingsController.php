<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SubscriptionGuard;
use App\Http\Controllers\Controller;
use App\Models\Memorial;
use App\Models\PaymentOrder;
use App\Models\SystemSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\NotificationService;
use App\Services\PaymentResultProcessor;
use App\Services\PesapalService;
use App\Support\PlanFeatures;
use App\Support\ProtectedRoles;
use App\Services\SystemMailConfigurator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        $oauth = SystemSetting::getByGroup('oauth');

        return view('pages.settings.general', [
            'title' => 'General Settings',
            'settings' => $settings,
            'oauth' => $oauth,
        ]);
    }

    public function updateGeneral(Request $request)
    {
        // Colors, fonts and the default theme are managed on the Appearance
        // page (Admin\AppearanceController).
        $request->validate([
            'oauth.google_enabled' => 'nullable|in:0,1',
            'oauth.google_client_id' => 'nullable|string|max:512',
            'oauth.google_client_secret' => 'nullable|string|max:512',
            'branding.app_name' => 'required|string|max:100',
            'branding.tagline' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'logo_dark' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:512',
        ]);

        foreach (['branding.app_name', 'branding.tagline'] as $key) {
            if ($request->has($key)) {
                SystemSetting::set($key, $request->input($key));
            }
        }

        SystemSetting::set('oauth.google_enabled', $request->boolean('oauth.google_enabled') ? '1' : '0');
        SystemSetting::set('oauth.google_client_id', trim((string) $request->input('oauth.google_client_id', '')));
        $googleSecret = $request->input('oauth.google_client_secret');
        if ($googleSecret && $googleSecret !== '••••••••') {
            SystemSetting::set('oauth.google_client_secret', $googleSecret);
        }

        if ($request->hasFile('logo')) {
            $previous = SystemSetting::get('branding.logo_path');
            if (is_string($previous) && $previous !== '' && Storage::disk('public')->exists($previous)) {
                Storage::disk('public')->delete($previous);
            }
            $path = $request->file('logo')->store('branding', 'public');
            SystemSetting::set('branding.logo_path', $path);
        }

        if ($request->hasFile('logo_dark')) {
            $previous = SystemSetting::get('branding.logo_dark_path');
            if (is_string($previous) && $previous !== '' && Storage::disk('public')->exists($previous)) {
                Storage::disk('public')->delete($previous);
            }
            $path = $request->file('logo_dark')->store('branding', 'public');
            SystemSetting::set('branding.logo_dark_path', $path);
        }

        if ($request->hasFile('favicon')) {
            $previous = SystemSetting::get('branding.favicon_path');
            if (is_string($previous) && $previous !== '' && Storage::disk('public')->exists($previous)) {
                Storage::disk('public')->delete($previous);
            }
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

    public function permissions(Request $request)
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();
        $users = User::with('roles')->orderBy('name')->get();

        return view('pages.settings.permissions', [
            'title' => 'Permissions',
            'roles' => $roles,
            // The per-user dropdown lists only what this admin may actually grant, so the
            // form cannot offer a choice updateUserRole() would then 403 on. $roles keeps
            // every role, because the permission matrix above still has to show them all.
            'assignableRoles' => ProtectedRoles::assignableQuery($request->user())->orderBy('name')->get(),
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

        // Same gate as Admin\UserController: this screen is reachable by any admin, and
        // without it the role dropdown here was a one-click self-promotion to super-admin.
        ProtectedRoles::guardTarget($request->user(), $user);
        ProtectedRoles::guardAssignment($request->user(), $request->role);

        $user->syncRoles([$request->role]);

        return back()->with('success', "Role updated for {$user->name}.");
    }

    public function destroyRole(Role $role)
    {
        if (in_array($role->name, ['super-admin', 'admin', 'user', 'reseller'])) {
            return back()->with('error', 'Cannot delete system roles.');
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }

    public function storePermission(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
        ]);

        Permission::create(['name' => $request->name, 'guard_name' => 'web']);

        return back()->with('success', 'Permission created.');
    }

    public function destroyPermission(Permission $permission)
    {
        $permission->delete();

        return back()->with('success', 'Permission deleted.');
    }

    /**
     * Replace a role's whole permission set from the submitted checkboxes. A role with no
     * boxes ticked submits no `permissions` key at all, which must clear the role rather than
     * leave it untouched — so an absent key is treated as an empty set.
     */
    public function updateRolePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "Permissions updated for the {$role->name} role.");
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

        // Pesapal rejects orders without a notification_id, so register the IPN URL as
        // soon as the credentials are in place rather than leaving checkout broken until
        // someone pastes an ID by hand.
        $message = 'Payment settings updated successfully.';
        if ($request->input('payments.pesapal_enabled') === '1'
            && empty(trim((string) SystemSetting::get('payments.pesapal_ipn_id', '')))) {
            $result = (new PesapalService)->registerIpn();
            $message .= $result['success']
                ? ' Pesapal IPN registered automatically (ID: '.$result['ipn_id'].').'
                : ' Pesapal IPN could not be registered: '.$result['error'];
        }

        return back()->with('success', $message);
    }

    /**
     * Register (or re-register) the Pesapal IPN URL and store the returned IPN ID.
     */
    public function registerPesapalIpn(Request $request)
    {
        $pesapal = new PesapalService;
        $result = $pesapal->registerIpn();

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'ipn_url' => $pesapal->getIpnUrl(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'ipn_id' => $result['ipn_id'],
            'ipn_url' => $result['url'],
            'message' => 'IPN registered with Pesapal.',
        ]);
    }

    // ─── Payment Orders (transactions) ──────────────────────────────────

    public function paymentOrders(Request $request)
    {
        $query = PaymentOrder::with(['user', 'plan', 'memorial', 'reseller'])->orderByDesc('created_at');

        $status = $request->query('status');
        if ($status && in_array($status, ['pending', 'completed', 'failed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        \App\Models\Reseller::applyFilter($query, $request->query('reseller'));

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
            'resellers' => \App\Models\Reseller::filterOptions(),
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

        if (! $plan->isFree()) {
            $guard = SubscriptionGuard::validatePayment($memorial, $plan);
            if (! $guard['allowed']) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'error' => $guard['reason']], 422);
                }
                return back()->with('error', $guard['reason']);
            }
        }

        $currency = SystemSetting::get('payments.currency', 'USD');
        $status = $gateway === 'pesapal' ? 'pending' : $request->status;

        $merchantRef = 'ADM-' . strtoupper(\Illuminate\Support\Str::random(8)) . '-' . time();
        $order = PaymentOrder::create([
            'user_id' => $request->user_id,
            'memorial_id' => $request->memorial_id,
            // Without this the order is orphaned from its tenant: invisible to the
            // reseller's revenue report and reconciled against the wrong credentials.
            'reseller_id' => $memorial->reseller_id,
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
            // The memorial's reseller's merchant account, exactly as the customer
            // checkout does — an admin creating the order must not reroute a
            // reseller's client's money into the platform's account.
            $pesapal = \App\Services\PesapalService::forReseller($memorial->reseller);
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
            'force_approve' => ['nullable', 'boolean'],
        ]);

        $memorial = Memorial::findOrFail($request->memorial_id);
        if ($memorial->user_id != $request->user_id) {
            return back()->with('error', 'Memorial must belong to the selected user.');
        }

        if ($request->status === 'completed' && $order->status !== 'completed' && ! $request->boolean('force_approve')) {
            $verificationError = $this->verifyWithPesapal($order);
            if ($verificationError !== null) {
                return back()->with('error', $verificationError);
            }
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
            'force_approve' => 'nullable|boolean',
        ]);

        $ids = $request->input('ids', []);
        $action = $request->input('action');
        $forceApprove = $request->boolean('force_approve');
        $orders = PaymentOrder::whereIn('id', $ids)->with(['user', 'plan'])->get();

        $count = 0;
        $skipped = 0;
        foreach ($orders as $order) {
            if ($action === 'approve') {
                if ($order->status !== 'completed') {
                    if (! $forceApprove && $this->verifyWithPesapal($order) !== null) {
                        $skipped++;
                        continue;
                    }
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
        if ($skipped > 0) {
            $message .= " {$skipped} skipped: Pesapal could not confirm payment. Use \"Skip Pesapal verification\" to approve offline payments.";
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    private function activateSubscriptionForOrder(PaymentOrder $order): void
    {
        app(PaymentResultProcessor::class)->activateSubscription($order);
    }

    /**
     * Confirm with Pesapal before an admin approval activates a subscription.
     * Returns an error message when approval should be blocked, or null when
     * the order is verified or has nothing to verify against (manual/offline).
     */
    private function verifyWithPesapal(PaymentOrder $order): ?string
    {
        if ($order->payment_gateway !== 'pesapal' || empty($order->order_tracking_id)) {
            return null;
        }

        // The order's own merchant account: verifying a reseller-paid order against
        // platform credentials returns nothing and blocks a genuinely paid order.
        $pesapal = PesapalService::forReseller($order->reseller);
        if (! $pesapal->isEnabled()) {
            return null;
        }

        $status = $pesapal->getTransactionStatus($order->order_tracking_id);
        if ($status === null) {
            return "Could not reach Pesapal to verify order {$order->merchant_reference}. Try again, or tick \"Skip Pesapal verification\" if this was paid offline.";
        }

        if (! $pesapal->isPaymentCompleted($status)) {
            $desc = $status['payment_status_description'] ?? 'not completed';
            return "Approval blocked: Pesapal reports order {$order->merchant_reference} as \"{$desc}\". Tick \"Skip Pesapal verification\" to override for offline payments.";
        }

        return null;
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

    /**
     * Retry every failed job, from the dashboard rather than a shell.
     *
     * The health banner used to end at "run php artisan queue:retry all", which assumes a
     * terminal on the server. On managed hosting there often is not one, so the warning was
     * a dead end and the count simply grew.
     *
     * Retrying does not fix anything by itself: these jobs failed for a reason, and if that
     * reason still stands they will fail again and the count will come back. The banner says
     * so next to the button.
     */
    public function retryFailedJobs()
    {
        $before = \App\Helpers\QueueHealthHelper::failedJobsCount();

        if ($before === 0) {
            return back()->with('success', 'There are no failed jobs to retry.');
        }

        \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('success', "{$before} failed ".\Illuminate\Support\Str::plural('job', $before)." queued to run again. If the cause has not been fixed they will fail a second time — check back in a minute.");
    }

    /**
     * Delete every failed job.
     *
     * Destructive and deliberately separate from retry: this throws the work away rather
     * than reattempting it, which is what an admin wants once the failures are understood
     * and known to be stale — old notification emails from before SMTP was configured, say,
     * which nobody wants delivered days late.
     */
    public function clearFailedJobs()
    {
        $before = \App\Helpers\QueueHealthHelper::failedJobsCount();

        if ($before === 0) {
            return back()->with('success', 'There are no failed jobs to clear.');
        }

        \Illuminate\Support\Facades\Artisan::call('queue:flush');

        Log::info('Failed jobs cleared from the admin dashboard', [
            'count' => $before,
            'by' => request()->user()?->id,
        ]);

        return back()->with('success', "{$before} failed ".\Illuminate\Support\Str::plural('job', $before)." deleted. They are gone for good — anything they would have sent will not be sent.");
    }

    /**
     * Send one real email through the saved SMTP settings, in the request, and report what
     * happened.
     *
     * Deliberately not queued. A queued test would report "sent" the moment it was accepted
     * onto the queue and hide the failure in a worker log — which is the situation this
     * button exists to end. Sending inline is slower and is the entire point: the exception
     * the mail server raises is the answer, so it is shown verbatim rather than flattened
     * into "could not send".
     *
     * It tests what is *saved*, not what is typed in the form above it. Anything else would
     * report on a configuration the application is not actually using.
     */
    public function sendTestSmtpEmail(Request $request)
    {
        $validated = $request->validate([
            'test_email' => ['nullable', 'email', 'max:255'],
        ]);

        $to = $validated['test_email'] ?? $request->user()->email;

        if (! $to) {
            return back()->with('smtp_test_error', 'No address to send to: this account has no email address, so enter one above.');
        }

        SystemMailConfigurator::applyFromSettings();

        // Distinguish "switched off" from "broken". Without this the admin gets a driver
        // error for what is really an unticked checkbox two fields above the button.
        if (! (bool) SystemSetting::get('smtp.enabled', false)) {
            return back()->with('smtp_test_error', 'SMTP is switched off, so nothing was sent. Turn on "Enable SMTP" above, save, then test again.');
        }

        if (empty(SystemSetting::get('smtp.host'))) {
            return back()->with('smtp_test_error', 'No SMTP host is saved, so nothing was sent. Fill in the host above, save, then test again.');
        }

        $host = (string) SystemSetting::get('smtp.host');
        $port = (string) SystemSetting::get('smtp.port', 587);
        $encryption = (string) SystemSetting::get('smtp.encryption', 'tls');
        $sentAt = now()->toDayDateTimeString();
        $appName = \App\Helpers\BrandingHelper::displayNameFor(null);

        $body = view('emails.smtp-test', [
            'appName' => $appName,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'sentAt' => $sentAt,
            'to' => $to,
        ])->render();

        try {
            Mail::html($body, function ($message) use ($to, $appName) {
                $message->to($to)->subject("{$appName} - SMTP test");
            });
        } catch (\Throwable $e) {
            Log::warning('SMTP test email failed', ['to' => $to, 'host' => $host, 'error' => $e->getMessage()]);

            return back()
                ->with('smtp_test_error', 'The mail server refused the message. Its own words are below — that text is the fault, not a generic failure.')
                ->with('smtp_test_detail', $e->getMessage());
        }

        return back()->with('success', "Test email sent to {$to} via {$host}:{$port} ({$encryption}). If it does not arrive within a minute or two, check the spam folder and the from-address — the connection itself worked.");
    }

    // ─── Notification Settings ──────────────────────────────────────

    public function notifications()
    {
        $settings = SystemSetting::getByGroup('notifications');
        $pushExtensionOk = \App\Services\NotificationService::hasPushMathExtension();

        return view('pages.settings.notifications', [
            'title' => 'Notification Settings',
            'settings' => $settings,
            'pushExtensionOk' => $pushExtensionOk,
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
        if ($status && in_array($status, ['active', 'cancelled', 'expired', 'paused', 'pending', 'overdue'], true)) {
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
            'status' => 'required|in:active,cancelled,expired,paused,pending,overdue',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'payment_gateway' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $memorial = Memorial::findOrFail($request->memorial_id);
        if ($memorial->user_id != $request->user_id) {
            return back()->with('error', 'Memorial must belong to the selected user.');
        }

        if ($request->status === 'active') {
            $plan = SubscriptionPlan::find($request->subscription_plan_id);
            $existingActive = UserSubscription::where('memorial_id', $memorial->id)
                ->where('subscription_plan_id', $request->subscription_plan_id)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->exists();

            if ($existingActive) {
                return back()->with('error', 'This memorial already has an active subscription for this plan.');
            }

            SubscriptionGuard::expireActiveSubscriptions($memorial);
            UserSubscription::where('memorial_id', $memorial->id)
                ->where('status', 'overdue')
                ->update(['status' => 'expired']);
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

        $plan = $plan ?? SubscriptionPlan::find($request->subscription_plan_id);
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
            'status' => 'required|in:active,cancelled,expired,paused,pending,overdue',
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

    // Custom-domain settings moved to Admin\ResellerSettingsController — they're part of
    // the reseller program, not platform-wide config, and now live on its Settings page.

    // ─── System Updates ─────────────────────────────────────────────

    public function updates()
    {
        $updaterController = app(\App\Http\Controllers\Admin\LaraUpdaterController::class);
        $currentVersion = $updaterController->getCurrentVersion();

        $updateAvailable = null;
        try {
            $checkResponse = $updaterController->check();
            $data = json_decode($checkResponse->getContent(), true);
            if (!empty($data['version'])) {
                $updateAvailable = $data;
            }
        } catch (\Throwable $e) {
            // silently fail
        }

        $updateBaseUrl = config('laraupdater.update_baseurl', url('/updates'));

        return view('pages.settings.updates', [
            'title' => 'System Updates',
            'currentVersion' => $currentVersion,
            'updateAvailable' => $updateAvailable,
            'updateBaseUrl' => $updateBaseUrl,
        ]);
    }

    // ─── Plans ───────────────────────────────────────────────────────

    public function plans(Request $request)
    {
        // Defaults to the platform's own plans. Without this the list silently mixes in
        // every reseller's client-facing plans, which an admin does not manage from here.
        $query = SubscriptionPlan::with('reseller')->orderBy('sort_order');
        \App\Models\Reseller::applyFilter($query, $request->query('reseller', 'direct'));

        return view('pages.settings.plans', [
            'title' => 'Subscription Plans',
            'plans' => $query->get(),
            'currency' => SystemSetting::get('payments.currency', 'USD'),
            'resellers' => \App\Models\Reseller::filterOptions(),
        ]);
    }

    public function storePlan(Request $request)
    {
        // Entitlement rules come from the catalogue so a new one cannot be saveable in one
        // of the two plan screens and rejected in the other.
        $request->validate(array_merge([
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50|unique:subscription_plans,slug',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly,lifetime',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer|min:0',
        ], PlanFeatures::rules()));

        $plan = SubscriptionPlan::create(array_merge($request->only([
            'name', 'slug', 'description', 'price', 'interval', 'is_active', 'sort_order',
            ...PlanFeatures::columns(),
        ]), ['is_popular' => $request->boolean('is_popular')]));

        if ($plan->is_popular) {
            $plan->makeSolePopular();
        }

        return back()->with('success', 'Plan created successfully.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $request->validate(array_merge([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly,lifetime',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer|min:0',
        ], PlanFeatures::rules()));

        $plan->update(array_merge($request->only([
            'name', 'description', 'price', 'interval', 'is_active', 'sort_order',
            ...PlanFeatures::columns(),
        ]), ['is_popular' => $request->boolean('is_popular')]));

        if ($plan->is_popular) {
            $plan->makeSolePopular();
        }

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
