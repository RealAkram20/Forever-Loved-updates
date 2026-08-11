<?php

use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResellerController;
use App\Http\Controllers\Admin\ResellerSettingsController;
use App\Http\Controllers\Admin\ResellerTierController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SiteLayoutController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemorialApiController;
use App\Http\Controllers\MemorialController;
use App\Http\Controllers\MemorialDirectoryController;
use App\Http\Controllers\MemorialGalleryCategoryController;
use App\Http\Controllers\MemorialMediaController;
use App\Http\Controllers\MemorialSignupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicMemorialController;
use App\Http\Controllers\Reseller\AnalyticsController;
use App\Http\Controllers\Reseller\ClientController;
use App\Http\Controllers\Reseller\PaymentSettingsController;
use App\Http\Controllers\Reseller\PlanController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WidgetController;
use App\Http\Middleware\EmbedFrameHeaders;
use App\Http\Middleware\EnsureResellerActive;
use App\Http\Middleware\ResolveReseller;
use App\Http\Middleware\ResolveResellerByCustomDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes (login, register, password reset, etc.)
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Reseller root pages — MUST precede the platform home route below
|--------------------------------------------------------------------------
| Laravel matches routes in registration order, and a route with no domain constraint
| matches ANY host. So `Route::get('/')` below was answering acme.<base domain>/ with the
| platform's landing page — our logo, our copy, our pricing, on a reseller's white-labeled
| domain. Registering these two first is what makes the reseller root theirs.
|
| Only the roots are hoisted. The `/{slug}` memorial routes stay far below, after the auth
| and public pages, so /login, /about and /pricing keep resolving to first-party routes on a
| reseller host instead of being read as memorial slugs.
|
| $foreignDomainPattern is defined here rather than beside the {slug} routes because both
| pairs need it and it must exist before the first use.
*/
$appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
$resellerBaseDomain = config('reseller.domain');

// Every hostname that is *us*. Only the exact APP_URL host was excluded before, so a site
// reachable at both example.com and www.example.com had its www address read as a foreign
// custom domain: the catch-all group below claimed `/`, ResolveResellerByCustomDomain found
// no reseller for it, and the home page 404'd for anyone who typed the www form.
$ownHosts = \App\Support\ResellerDomain::platformHosts($appHost);

$foreignDomainPattern = '^'
    .implode('', array_map(fn (string $host) => '(?!'.preg_quote($host, '#').'$)', $ownHosts))
    .'(?!'.preg_quote($resellerBaseDomain, '#').'$)'
    .'(?!.*\.'.preg_quote($resellerBaseDomain, '#').'$).+$';

// www.<base> is the platform, not a reseller — but once wildcard DNS routes it here, the
// {reseller} group below would claim its root and 404 it as an unknown slug ('www' is a
// reserved slug, so no reseller can legitimately be there either). Registered first so it
// wins, and only when APP_URL's host is the bare apex — a www-canonical install has
// nothing to redirect. 301 to the same path on the apex, query string included.
if ($appHost === $resellerBaseDomain) {
    Route::domain('www.'.$resellerBaseDomain)->group(function () {
        Route::get('/{any?}', function (?string $any = null) {
            $query = request()->getQueryString();

            return redirect()->to(
                rtrim((string) config('app.url'), '/').($any !== null && $any !== '' ? '/'.ltrim($any, '/') : '').($query ? '?'.$query : ''),
                301
            );
        })->where('any', '.*');
    });
}

Route::domain('{reseller}.'.$resellerBaseDomain)->group(function () {
    Route::get('/', [PublicMemorialController::class, 'indexForReseller'])
        ->name('reseller.public.index')
        ->where('reseller', '[a-z0-9\-]+')
        ->middleware(ResolveReseller::class);
});

Route::domain('{domain}')->group(function () use ($foreignDomainPattern) {
    Route::get('/', [PublicMemorialController::class, 'indexForReseller'])
        ->name('reseller.public.index-custom-domain')
        ->where('domain', $foreignDomainPattern)
        ->middleware(ResolveResellerByCustomDomain::class);
});

// Landing / Home page (public). Reachable on the app's own host only — the two groups above
// claim the root on reseller hosts, and their patterns exclude this one.
Route::get('/', [PageController::class, 'home'])->name('home');

// AJAX memorial search (public)
Route::get('/api/search/memorials', [MemorialController::class, 'search'])->middleware('throttle:60,1')->name('memorials.search');

// Find Memorial directory (public)
Route::get('/find-memorial', [MemorialDirectoryController::class, 'index'])->name('memorial.directory');

// Public visitor pages
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
Route::get('/terms-of-use', [PageController::class, 'termsOfUse'])->name('terms-of-use');
Route::get('/p/{slug}', fn (string $slug) => redirect("/{$slug}", 301))->where('slug', '[a-z0-9\-]+');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:10,1')->name('contact.send');

// Memorial creation flow (multi-step signup)
Route::prefix('create-memorial')->name('memorial.create.')->group(function () {
    Route::get('/step-1', [MemorialSignupController::class, 'step1'])->name('step1');
    Route::post('/step-1', [MemorialSignupController::class, 'storeStep1'])->name('storeStep1');
    Route::get('/step-2', [MemorialSignupController::class, 'step2'])->name('step2');
    Route::post('/step-2/register', [MemorialSignupController::class, 'storeStep2Register'])->name('storeStep2Register');
    Route::post('/step-2/login', [MemorialSignupController::class, 'storeStep2Login'])->name('storeStep2Login');
    Route::post('/check-email', [MemorialSignupController::class, 'checkEmail'])->middleware('throttle:30,1')->name('checkEmail');
    Route::get('/step-3', [MemorialSignupController::class, 'step3'])->name('step3');
    Route::post('/step-3', [MemorialSignupController::class, 'storeStep3'])->name('storeStep3');
    Route::post('/prepare-paid-checkout', [MemorialSignupController::class, 'preparePaidCheckout'])->name('preparePaidCheckout');
    Route::get('/complete', [MemorialSignupController::class, 'complete'])->name('complete');
    Route::get('/preparing/{slug}', [MemorialSignupController::class, 'preparing'])->name('preparing');
});

// Dashboard routes (protected)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(EnsureResellerActive::class)->name('dashboard');

    Route::post('memorials/{memorial}/status', [MemorialController::class, 'updateStatus'])->name('memorials.status');
    Route::patch('memorials/{memorial}/section', [MemorialController::class, 'updateSection'])->name('memorials.section');
    Route::patch('memorials/{memorial}/fields', [MemorialController::class, 'updateFields'])->name('memorials.fields');
    Route::post('memorials/{memorial}/generate-biography', [MemorialController::class, 'generateBiography'])->middleware('throttle:10,1')->name('memorials.generate-biography');
    Route::get('memorials/{memorial}/generate-biography/{requestId}', [MemorialController::class, 'generateBiographyStatus'])->where('requestId', '[0-9a-f\-]{36}')->name('memorials.generate-biography.status');
    Route::post('memorials/{memorial}/generate-template-biography', [MemorialController::class, 'generateTemplateBiography'])->middleware('throttle:10,1')->name('memorials.generate-template-biography');
    Route::patch('memorials/{memorial}/biography', [MemorialController::class, 'saveBiography'])->name('memorials.save-biography');
    Route::resource('memorials', MemorialController::class);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/dropdown', [NotificationController::class, 'dropdown'])->name('notifications.dropdown');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/push/subscribe', [NotificationController::class, 'subscribePush'])->name('notifications.push.subscribe');
    Route::post('/notifications/push/unsubscribe', [NotificationController::class, 'unsubscribePush'])->name('notifications.push.unsubscribe');
    Route::post('/notifications/push/reset', [NotificationController::class, 'resetPush'])->name('notifications.push.reset');
    Route::post('/notifications/push/test', [NotificationController::class, 'testPush'])->middleware('role:admin|super-admin')->name('notifications.push.test');

    // Subscription & Billing (user)
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/payment/create-order', [PaymentController::class, 'createOrder'])->name('payment.create-order');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::delete('/profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── One memorial's report ────────────────────────────────────────
    // Registered for every signed-in user rather than inside the admin or reseller groups:
    // the memorial's own family reaches it too. MemorialPolicy::report() decides — owner,
    // their editors, the reseller hosting it, and platform admins. Deliberately a narrower
    // gate than viewing the memorial itself, which any visitor may do.
    Route::get('/memorials/{slug}/report', [App\Http\Controllers\MemorialReportController::class, 'show'])
        ->where('slug', '[a-z0-9\-]+')->name('memorials.report');
    Route::get('/memorials/{slug}/report/download', [App\Http\Controllers\MemorialReportController::class, 'download'])
        ->where('slug', '[a-z0-9\-]+')->name('memorials.report.download');

    // Calendar
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::post('/calendar/events', [CalendarController::class, 'store'])->name('calendar.events.store');
    Route::put('/calendar/events/{calendarEvent}', [CalendarController::class, 'update'])->name('calendar.events.update');
    Route::delete('/calendar/events/{calendarEvent}', [CalendarController::class, 'destroy'])->name('calendar.events.destroy');

    // ─── Users Management (admin only) ──────────────────────────────
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);

        // ─── Reports ──────────────────────────────────────────────
        // Deliberately at /reports rather than under the /settings prefix: reports are
        // something an admin reads, not something they configure, and the nav gives them
        // their own Overview entry. Individual reports gate themselves further — the
        // reseller-billing one is super-admin only, enforced in the report class.
        //
        // {format} is whitelisted here as well as in ExporterFactory, so no request can
        // name a class. {report} resolves through the registry keyed by audience.
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])
            ->where('report', '[a-z0-9\-]+')->name('reports.show');
        Route::get('/reports/{report}/download/{format}', [ReportController::class, 'download'])
            ->where(['report' => '[a-z0-9\-]+', 'format' => 'pdf|xlsx|csv'])->name('reports.download');
    });

    // Admin push onboarding dismiss
    Route::post('/admin/dismiss-push-onboarding', function (Request $request) {
        $request->session()->put('admin_push_onboarding_dismissed', true);

        return response()->json(['success' => true]);
    })->middleware(['auth', 'role:admin|super-admin'])->name('admin.dismiss-push-onboarding');

    // ─── Admin Settings ──────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [SettingsController::class, 'general'])->name('general');
        Route::put('/general', [SettingsController::class, 'updateGeneral'])->name('general.update');

        Route::get('/appearance', [AppearanceController::class, 'index'])->name('appearance');
        Route::put('/appearance', [AppearanceController::class, 'update'])->name('appearance.update');
        Route::post('/appearance/fonts', [AppearanceController::class, 'storeFont'])->name('appearance.fonts.store');
        Route::delete('/appearance/fonts/{index}', [AppearanceController::class, 'destroyFont'])->whereNumber('index')->name('appearance.fonts.destroy');

        Route::get('/ai', [SettingsController::class, 'ai'])->name('ai');
        Route::put('/ai', [SettingsController::class, 'updateAi'])->name('ai.update');

        Route::get('/permissions', [SettingsController::class, 'permissions'])->name('permissions');
        Route::post('/permissions/roles', [SettingsController::class, 'storeRole'])->name('roles.store');
        Route::put('/permissions/users/{user}/role', [SettingsController::class, 'updateUserRole'])->name('users.role');
        Route::delete('/permissions/roles/{role}', [SettingsController::class, 'destroyRole'])->name('roles.destroy');
        // Create permissions and grant them to roles.
        Route::post('/permissions/permissions', [SettingsController::class, 'storePermission'])->name('permissions.store');
        Route::delete('/permissions/permissions/{permission}', [SettingsController::class, 'destroyPermission'])->name('permissions.destroy')->whereNumber('permission');
        Route::put('/permissions/roles/{role}/permissions', [SettingsController::class, 'updateRolePermissions'])->name('roles.permissions')->whereNumber('role');

        Route::get('/payments', [SettingsController::class, 'payments'])->name('payments');
        Route::put('/payments', [SettingsController::class, 'updatePayments'])->name('payments.update');
        Route::post('/payments/register-ipn', [SettingsController::class, 'registerPesapalIpn'])->name('payments.register-ipn');

        Route::get('/payment-orders', [SettingsController::class, 'paymentOrders'])->name('payment-orders');
        Route::post('/payment-orders/bulk', [SettingsController::class, 'bulkPaymentOrders'])->name('payment-orders.bulk');
        Route::post('/payment-orders', [SettingsController::class, 'storePaymentOrder'])->name('payment-orders.store');
        Route::put('/payment-orders/{order}', [SettingsController::class, 'updatePaymentOrder'])->name('payment-orders.update');
        Route::delete('/payment-orders/{order}', [SettingsController::class, 'destroyPaymentOrder'])->name('payment-orders.destroy');

        Route::get('/smtp', [SettingsController::class, 'smtp'])->name('smtp');
        Route::put('/smtp', [SettingsController::class, 'updateSmtp'])->name('smtp.update');

        Route::get('/notifications', [SettingsController::class, 'notifications'])->name('notifications');
        Route::put('/notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');

        Route::get('/subscriptions', [SettingsController::class, 'subscriptions'])->name('subscriptions');
        Route::post('/subscriptions', [SettingsController::class, 'storeSubscription'])->name('subscriptions.store');
        Route::put('/subscriptions/{subscription}', [SettingsController::class, 'updateSubscription'])->name('subscriptions.update');

        Route::get('/plans', [SettingsController::class, 'plans'])->name('plans');
        Route::post('/plans', [SettingsController::class, 'storePlan'])->name('plans.store');
        Route::put('/plans/{plan}', [SettingsController::class, 'updatePlan'])->name('plans.update');
        Route::delete('/plans/{plan}', [SettingsController::class, 'destroyPlan'])->name('plans.destroy');

        // Custom-domain config moved under the reseller program's own Settings page —
        // it was never platform-wide in any meaningful sense. Kept as a redirect so
        // existing bookmarks don't 404.
        Route::get('/domains', fn () => redirect()->route('settings.reseller-settings'))->name('domains');

        Route::get('/updates', [SettingsController::class, 'updates'])->name('updates');

        Route::get('/menus', [MenuController::class, 'edit'])->name('menus.edit');
        Route::post('/menus/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
        Route::put('/menus/items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
        Route::delete('/menus/items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');
        Route::post('/menus/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');

        Route::get('/site-layout/{key}/edit', [SiteLayoutController::class, 'edit'])->name('site-layout.edit');
        Route::put('/site-layout/{key}', [SiteLayoutController::class, 'update'])->name('site-layout.update');

        // Editable Pages (route-based SEO lives under /pages/seo/..., not a separate Settings section)
        Route::get('/pages', [App\Http\Controllers\Admin\PageController::class, 'index'])->name('pages.index');
        Route::get('/pages/create', [App\Http\Controllers\Admin\PageController::class, 'create'])->name('pages.create');
        Route::post('/pages', [App\Http\Controllers\Admin\PageController::class, 'store'])->name('pages.store');
        Route::post('/pages/preview', [App\Http\Controllers\Admin\PageController::class, 'preview'])->name('pages.preview');
        Route::get('/pages/seo/{routeKey}/edit', [App\Http\Controllers\Admin\PageController::class, 'editSeoRoute'])->name('pages.seo.edit')->where('routeKey', '[A-Za-z0-9._\-]+');
        Route::put('/pages/seo/{routeKey}', [App\Http\Controllers\Admin\PageController::class, 'updateSeoRoute'])->name('pages.seo.update')->where('routeKey', '[A-Za-z0-9._\-]+');
        Route::get('/pages/{slug}/layout', [App\Http\Controllers\Admin\PageController::class, 'editLayout'])->name('pages.layout.edit');
        Route::put('/pages/{slug}/layout', [App\Http\Controllers\Admin\PageController::class, 'updateLayout'])->name('pages.layout.update');
        Route::post('/pages/{slug}/meta', [App\Http\Controllers\Admin\PageController::class, 'updatePageMeta'])->name('pages.meta.update');
        Route::get('/pages/{slug}/edit', [App\Http\Controllers\Admin\PageController::class, 'edit'])->name('pages.edit');
        Route::delete('/pages/{slug}', [App\Http\Controllers\Admin\PageController::class, 'destroy'])->name('pages.destroy');

        // ─── Reseller program (super-admin only) ──────────────────────
        // Genuinely super-admin, not the enclosing role:admin|super-admin. These actions
        // impersonate a reseller owner, suspend a live business, reassign every client and
        // memorial they hold, and record money. The comment used to claim super-admin while
        // the middleware allowed any admin; the middleware now matches the claim.
        Route::middleware('role:super-admin')->group(function () {
            // Three destinations, each with its own nav entry: the roster (/resellers),
            // what we charge them (/reseller-pricing), and how the program is configured
            // (/reseller-settings). Pricing and settings deliberately sit on sibling paths
            // rather than under /resellers/* so the roster can claim that whole prefix and
            // stay highlighted on a per-reseller detail page.
            Route::get('/resellers', [ResellerController::class, 'index'])->name('resellers');
            Route::post('/resellers', [ResellerController::class, 'store'])->name('resellers.store');
            Route::get('/resellers/{reseller}', [ResellerController::class, 'show'])->name('resellers.show')->whereNumber('reseller');
            Route::put('/resellers/{reseller}', [ResellerController::class, 'update'])->name('resellers.update');
            Route::post('/resellers/{reseller}/suspend', [ResellerController::class, 'suspend'])->name('resellers.suspend');
            Route::post('/resellers/{reseller}/activate', [ResellerController::class, 'activate'])->name('resellers.activate');
            Route::post('/resellers/{reseller}/rollover', [ResellerController::class, 'rollover'])->name('resellers.rollover');
            Route::post('/resellers/{reseller}/verify-domain', [ResellerController::class, 'verifyDomain'])->name('resellers.verify-domain');
            Route::put('/resellers/{reseller}/custom-domain', [ResellerController::class, 'setCustomDomain'])->name('resellers.custom-domain.set');
            Route::post('/resellers/{reseller}/custom-domain/check', [ResellerController::class, 'checkCustomDomain'])->name('resellers.custom-domain.check');
            Route::delete('/resellers/{reseller}/custom-domain', [ResellerController::class, 'clearCustomDomain'])->name('resellers.custom-domain.clear');
            Route::post('/resellers/{reseller}/restore', [ResellerController::class, 'restore'])->name('resellers.restore');
            Route::post('/resellers/{reseller}/login-as', [ResellerController::class, 'loginAs'])->name('resellers.login-as');
            Route::post('/resellers/{reseller}/payments', [ResellerController::class, 'recordPayment'])->name('resellers.payments.store');

            Route::get('/reseller-pricing', [ResellerTierController::class, 'index'])->name('reseller-pricing');
            Route::post('/reseller-tiers', [ResellerTierController::class, 'store'])->name('reseller-tiers.store');
            Route::put('/reseller-tiers/{resellerTier}', [ResellerTierController::class, 'update'])->name('reseller-tiers.update');
            Route::delete('/reseller-tiers/{resellerTier}', [ResellerTierController::class, 'destroy'])->name('reseller-tiers.destroy');

            Route::get('/reseller-settings', [ResellerSettingsController::class, 'edit'])->name('reseller-settings');
            Route::put('/reseller-settings', [ResellerSettingsController::class, 'update'])->name('reseller-settings.update');
        });
    });

    // Lets a super-admin return to their own account after using "Login as" on a reseller.
    // Deliberately outside the role:reseller-gated group below — reachable while
    // authenticated AS the impersonated reseller owner, who has no admin role.
    Route::post('/reseller/stop-impersonating', [ResellerController::class, 'stopImpersonating'])->name('reseller.stop-impersonating');

    // ─── Reseller staff area ──────────────────────────────────────────
    Route::prefix('reseller')->name('reseller.')->middleware(['role:reseller', EnsureResellerActive::class])->group(function () {
        // The dashboard itself lives at /dashboard (see the 'dashboard' route above), which
        // delegates to Reseller\DashboardController::index() directly for reseller staff —
        // no reason to expose a second, redundant URL for the same page. This bare /reseller
        // redirect just keeps old links/bookmarks working.
        Route::get('/', fn () => redirect()->route('dashboard'));
        Route::get('/memorials', [App\Http\Controllers\Reseller\DashboardController::class, 'memorials'])->name('memorials');
        Route::get('/memorials/create', [App\Http\Controllers\Reseller\DashboardController::class, 'createMemorial'])->name('memorials.create');
        Route::post('/memorials', [App\Http\Controllers\Reseller\DashboardController::class, 'storeMemorial'])->name('memorials.store');

        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

        // ─── Reports ──────────────────────────────────────────────────
        // Same three endpoints the admin has, resolved against the reseller audience. The
        // tenant is never a route parameter: report classes receive the Reseller that
        // EnsureResellerActive bound from the authenticated user's own reseller_id.
        Route::get('/reports', [App\Http\Controllers\Reseller\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [App\Http\Controllers\Reseller\ReportController::class, 'show'])
            ->where('report', '[a-z0-9\-]+')->name('reports.show');
        Route::get('/reports/{report}/download/{format}', [App\Http\Controllers\Reseller\ReportController::class, 'download'])
            ->where(['report' => '[a-z0-9\-]+', 'format' => 'pdf|xlsx|csv'])->name('reports.download');

        Route::get('/clients', [ClientController::class, 'index'])->name('clients');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::put('/clients/{user}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{user}', [ClientController::class, 'destroy'])->name('clients.destroy');

        // Reseller's own staff (the 'reseller' role) — owner-only, enforced in the controller.
        Route::get('/staff', [App\Http\Controllers\Reseller\StaffController::class, 'index'])->name('staff');
        Route::post('/staff', [App\Http\Controllers\Reseller\StaffController::class, 'store'])->name('staff.store');
        Route::delete('/staff/{user}', [App\Http\Controllers\Reseller\StaffController::class, 'destroy'])->name('staff.destroy')->whereNumber('user');

        Route::get('/plans', [PlanController::class, 'index'])->name('plans');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('/settings', [App\Http\Controllers\Reseller\SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings', [App\Http\Controllers\Reseller\SettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/domain', [App\Http\Controllers\Reseller\SettingsController::class, 'updateCustomDomain'])->name('settings.domain.update');
        Route::post('/settings/domain/verify', [App\Http\Controllers\Reseller\SettingsController::class, 'verifyCustomDomain'])->name('settings.domain.verify');

        // Appearance replaced the old Branding page (logo + favicon + one colour) with the
        // full colour and font set. /branding is kept as a redirect so existing links,
        // bookmarks and the "More Settings" card keep working.
        Route::get('/appearance', [App\Http\Controllers\Reseller\AppearanceController::class, 'edit'])->name('appearance');
        Route::put('/appearance', [App\Http\Controllers\Reseller\AppearanceController::class, 'update'])->name('appearance.update');
        Route::delete('/appearance/reset', [App\Http\Controllers\Reseller\AppearanceController::class, 'reset'])->name('appearance.reset');
        Route::get('/branding', fn () => redirect()->route('reseller.appearance'))->name('branding');

        Route::get('/payments', [PaymentSettingsController::class, 'edit'])->name('payments');
        Route::put('/payments', [PaymentSettingsController::class, 'update'])->name('payments.update');
        Route::post('/payments/register-ipn', [PaymentSettingsController::class, 'registerIpn'])->name('payments.register-ipn');

        // The reseller's own website: the same page builder the admin has, scoped to this
        // tenant. Gated inside the controller by the tier's feature_page_builder flag —
        // the index shows the pitch when it's locked, writes 403. `create` precedes the
        // {slug} routes so it is never read as a page slug.
        // The navigation on their own site. Gated inside the controller on the same rule as
        // the page builder — menus point at those pages, so the two travel together.
        Route::get('/menus', [App\Http\Controllers\Reseller\MenuController::class, 'edit'])->name('menus.edit');
        Route::post('/menus/items', [App\Http\Controllers\Reseller\MenuController::class, 'storeItem'])->name('menus.items.store');
        Route::put('/menus/items/{item}', [App\Http\Controllers\Reseller\MenuController::class, 'updateItem'])->name('menus.items.update')->whereNumber('item');
        Route::delete('/menus/items/{item}', [App\Http\Controllers\Reseller\MenuController::class, 'destroyItem'])->name('menus.items.destroy')->whereNumber('item');
        Route::post('/menus/reorder', [App\Http\Controllers\Reseller\MenuController::class, 'reorder'])->name('menus.reorder');

        Route::get('/pages', [App\Http\Controllers\Reseller\PageController::class, 'index'])->name('pages.index');
        Route::get('/pages/homepage', [App\Http\Controllers\Reseller\PageController::class, 'editHome'])->name('pages.home');
        // Standard pages (About, Pricing, Contact, …) are switched on and off rather than
        // created and deleted. Declared before the {slug} routes so 'standard' is never read
        // as a page slug.
        Route::put('/pages/standard/{slug}', [App\Http\Controllers\Reseller\PageController::class, 'toggleStandard'])
            ->name('pages.standard.toggle')->where('slug', '[a-z0-9\-]+');
        Route::get('/pages/create', [App\Http\Controllers\Reseller\PageController::class, 'create'])->name('pages.create');
        Route::post('/pages', [App\Http\Controllers\Reseller\PageController::class, 'store'])->name('pages.store');
        Route::post('/pages/preview', [App\Http\Controllers\Reseller\PageController::class, 'preview'])->name('pages.preview');
        Route::get('/pages/{slug}/edit', [App\Http\Controllers\Reseller\PageController::class, 'edit'])->name('pages.edit')->where('slug', '[a-z0-9\-]+');
        Route::put('/pages/{slug}/layout', [App\Http\Controllers\Reseller\PageController::class, 'updateLayout'])->name('pages.layout.update')->where('slug', '[a-z0-9\-]+');
        Route::post('/pages/{slug}/meta', [App\Http\Controllers\Reseller\PageController::class, 'updatePageMeta'])->name('pages.meta.update')->where('slug', '[a-z0-9\-]+');
        Route::delete('/pages/{slug}', [App\Http\Controllers\Reseller\PageController::class, 'destroy'])->name('pages.destroy')->where('slug', '[a-z0-9\-]+');
    });
});

// Memorial API (AJAX - no page reload)
Route::prefix('m/{slug}')->where(['slug' => '[a-z0-9\-]+'])->name('memorial.api.')->group(function () {
    // Memorial team: invite / re-role / remove people who help manage this memorial. Auth
    // required (the actions themselves also enforce canManageTeam), unlike the public tribute
    // and reaction endpoints in this group.
    Route::middleware('auth')->group(function () {
        Route::get('/collaborators', [\App\Http\Controllers\MemorialCollaboratorController::class, 'index'])->name('collaborators.index');
        Route::post('/collaborators', [\App\Http\Controllers\MemorialCollaboratorController::class, 'store'])->middleware('throttle:12,1')->name('collaborators.store');
        Route::patch('/collaborators/{collaborator}', [\App\Http\Controllers\MemorialCollaboratorController::class, 'update'])->whereNumber('collaborator')->name('collaborators.update');
        Route::delete('/collaborators/{collaborator}', [\App\Http\Controllers\MemorialCollaboratorController::class, 'destroy'])->whereNumber('collaborator')->name('collaborators.destroy');
    });

    Route::patch('/section', [MemorialApiController::class, 'updateSection'])->name('section');
    // The guest-writable endpoints below carry throttles because they are reachable without
    // signing in: each one either creates rows, sends mail, or accepts uploads, and none of
    // them had a limit. The authenticated editor routes rely on the policy check instead.
    Route::post('/tribute', [MemorialApiController::class, 'storeTribute'])->middleware('throttle:20,1')->name('tribute');
    Route::post('/track-share', [MemorialApiController::class, 'trackShare'])->name('track-share');
    Route::get('/stats', [MemorialApiController::class, 'stats'])->name('stats');
    Route::post('/reaction', [MemorialApiController::class, 'storeReaction'])->middleware('throttle:60,1')->name('reaction');
    Route::get('/posts', [MemorialApiController::class, 'posts'])->name('posts');
    Route::post('/posts', [MemorialApiController::class, 'storePost'])->name('posts.store');
    Route::patch('/posts/{postId}', [MemorialApiController::class, 'updatePost'])->name('posts.update');
    Route::delete('/posts/{postId}', [MemorialApiController::class, 'deletePost'])->name('posts.delete');
    Route::get('/posts/{postId}/comments', [MemorialApiController::class, 'comments'])->name('posts.comments');
    Route::post('/posts/{postId}/comments', [MemorialApiController::class, 'storeComment'])->middleware('throttle:30,1')->name('posts.comments.store');
    Route::get('/comments/{commentId}/replies', [MemorialApiController::class, 'commentReplies'])->name('comments.replies');
    Route::delete('/comments/{commentId}', [MemorialApiController::class, 'deleteComment'])->name('comments.delete');
    Route::get('/posts/{postId}/reactions', [MemorialApiController::class, 'reactions'])->name('posts.reactions');
    Route::get('/tributes', [MemorialApiController::class, 'tributes'])->name('tributes');
    Route::patch('/tributes/{tributeId}', [MemorialApiController::class, 'updateTribute'])->name('tributes.update');
    Route::delete('/tributes/{tributeId}', [MemorialApiController::class, 'deleteTribute'])->name('tributes.delete');
    Route::get('/chapters', [MemorialApiController::class, 'chapters'])->name('chapters');
    Route::post('/chapters', [MemorialApiController::class, 'storeChapter'])->name('chapters.store');
    Route::patch('/chapters/{chapterId}', [MemorialApiController::class, 'updateChapter'])->name('chapters.update');
    Route::delete('/chapters/{chapterId}', [MemorialApiController::class, 'deleteChapter'])->name('chapters.delete');
    // Memorial subscriptions
    Route::post('/subscribe', [MemorialApiController::class, 'subscribe'])->middleware('throttle:20,1')->name('subscribe');
    Route::put('/subscribe', [MemorialApiController::class, 'updateSubscription'])->name('subscribe.update');
    Route::delete('/subscribe', [MemorialApiController::class, 'unsubscribe'])->name('subscribe.delete');
    Route::get('/subscribe/check', [MemorialApiController::class, 'checkSubscription'])->name('subscribe.check');
    // Media uploads
    Route::post('/profile-photo', [MemorialMediaController::class, 'uploadProfilePhoto'])->name('profile-photo');
    Route::post('/cover-photo', [MemorialMediaController::class, 'uploadCoverPhoto'])->name('cover-photo');
    Route::delete('/cover-photo', [MemorialMediaController::class, 'removeCoverPhoto'])->name('cover-photo.delete');
    Route::post('/gallery', [MemorialMediaController::class, 'uploadGalleryMedia'])->name('gallery');
    Route::patch('/gallery/{mediaId}', [MemorialMediaController::class, 'updateGalleryMedia'])->name('gallery.update');
    Route::delete('/gallery/{mediaId}', [MemorialMediaController::class, 'deleteGalleryMedia'])->name('gallery.delete');
    // Gallery categories — the family's own divisions of the grid. Editors only; the guard
    // lives in the controller, as it does for every other route in this group.
    Route::post('/gallery-categories', [MemorialGalleryCategoryController::class, 'store'])->name('gallery-categories.store');
    Route::patch('/gallery-categories/{categoryId}', [MemorialGalleryCategoryController::class, 'update'])->whereNumber('categoryId')->name('gallery-categories.update');
    Route::delete('/gallery-categories/{categoryId}', [MemorialGalleryCategoryController::class, 'destroy'])->whereNumber('categoryId')->name('gallery-categories.delete');
    Route::post('/post-media', [MemorialMediaController::class, 'uploadPostMedia'])->name('post-media');
    Route::post('/tribute-post', [MemorialMediaController::class, 'storeTributePost'])->middleware('throttle:10,1')->name('tribute-post');
    Route::post('/background-music', [MemorialMediaController::class, 'uploadBackgroundMusic'])->name('background-music');
    Route::delete('/background-music', [MemorialMediaController::class, 'removeBackgroundMusic'])->name('background-music.delete');
});

// Embed widget (public, unauthenticated) - read-only memorial view for iframe embedding
// on a reseller's own external site, via public/embed.js.
Route::get('/widget/{slug}', [WidgetController::class, 'show'])
    ->name('widget.show')
    ->where('slug', '[a-z0-9\-]+')
    ->middleware(EmbedFrameHeaders::class);

// Path-based fallback for the reseller memorial page, for environments that cannot do
// host-header routing at all: a subdirectory install (APP_URL ending in /Forever) has no
// URL that reaches a Route::domain() group, and an APP_URL host that isn't the reseller
// base domain means every {slug}.{base} address is dead. Reseller::publicBaseUrl() hands
// this out in exactly those cases, so reseller pages are reachable in development rather
// than existing only in production. Same controller, same tenant scoping, same middleware.
// The base address itself, which every reseller screen shows and offers to copy. Registered
// before the /{slug} variant below so the bare form is not read as a memorial slug.
Route::get('/r/{reseller}', [PublicMemorialController::class, 'indexForReseller'])
    ->name('reseller.public.index-path')
    ->where('reseller', '[a-z0-9\-]+')
    ->middleware(ResolveReseller::class);

// Reseller-scoped auth for the path fallback (dev / subdirectory installs), so a reseller's
// clients can sign in and register inside the reseller's own space instead of on the platform
// site. On real subdomains / custom domains the shared auth routes already resolve on the
// reseller host (ResolveResellerByHost binds the tenant there), so these exist purely for
// environments without host routing. MUST precede the /r/{reseller}/{slug} memorial route
// below so 'login' and 'register' are matched here, not read as memorial slugs.
Route::prefix('r/{reseller}')
    ->where(['reseller' => '[a-z0-9\-]+'])
    ->middleware(ResolveReseller::class)
    ->group(function () {
        // Their directory, listing their memorials.
        //
        // Without this the path fell through to the /r/{reseller}/{slug} memorial route
        // below, found no memorial called "find-memorial", and served their *page* — which
        // rendered the directory widget, whose JS then fetched the platform's endpoint on
        // the platform's host with no tenant bound. So a reseller's own directory answered
        // with every memorial except theirs. It needs to be a real route with the tenant
        // resolved, not a page that happens to contain a search box.
        Route::get('find-memorial', [MemorialDirectoryController::class, 'index'])
            ->name('reseller.memorial.directory');

        // The header search box, for the same reason. MemorialController@search is already
        // tenant-scoped, but nothing bound a tenant to it under this fallback, so it fell
        // back to the platform's memorials for any visitor who was not signed in.
        Route::get('api/search/memorials', [MemorialController::class, 'search'])
            ->middleware('throttle:60,1')
            ->name('reseller.memorials.search');

        Route::middleware('guest')->group(function () {
            Route::get('login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'create'])->name('reseller.login');
            Route::post('login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
            Route::get('register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])->name('reseller.register');
            Route::post('register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
        });
    });

Route::get('/r/{reseller}/{slug}', [PublicMemorialController::class, 'showForReseller'])
    ->name('memorial.public.reseller-path')
    ->where(['reseller' => '[a-z0-9\-]+', 'slug' => '[a-z0-9\-]+'])
    ->middleware(ResolveReseller::class);

// Reseller white-labeled subdomain (e.g. acme.foreverloved.com) - public memorial pages
// only, scoped strictly to that reseller's own memorials. Matches on Host header, so it
// never competes with the apex catch-all route below.
// The root of this group is registered near the top of this file instead — it has to precede
// the platform's own `/` route, which would otherwise claim it on every host.
Route::domain('{reseller}.'.$resellerBaseDomain)->group(function () {
    Route::get('/{slug}', [PublicMemorialController::class, 'showForReseller'])
        ->name('memorial.public.reseller')
        ->where(['reseller' => '[a-z0-9\-]+', 'slug' => '[a-z0-9\-]+'])
        ->middleware(ResolveReseller::class);
});

// Reseller's own verified custom domain (e.g. memorials.acmefuneral.com) — same public
// memorial page, resolved by Host header against `resellers.custom_domain` instead of
// the subdomain pattern above. {domain} would otherwise match ANY hostname — including
// this app's own (e.g. "localhost", or *.foreverloved.com already handled above) — which
// would shadow routes like /dashboard or /login for our own site, since Route::domain()
// groups don't automatically defer to non-domain-restricted routes registered elsewhere.
// The exclusion regex ($foreignDomainPattern, defined near the top of this file alongside the
// root routes that also need it) is what actually keeps this scoped to genuinely foreign hosts.
Route::domain('{domain}')->group(function () use ($foreignDomainPattern) {
    Route::get('/{slug}', [PublicMemorialController::class, 'showForReseller'])
        ->name('memorial.public.custom-domain')
        ->where(['slug' => '[a-z0-9\-]+', 'domain' => $foreignDomainPattern])
        ->middleware(ResolveResellerByCustomDomain::class);
});

// Payment callback & IPN (no auth - Pesapal redirects/IPN calls)
Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::get('/payment/complete', [PaymentController::class, 'complete'])->name('payment.complete');
Route::match(['get', 'post'], '/payment/ipn', [PaymentController::class, 'ipn'])->name('payment.ipn');

// Install routes (must be before the catch-all slug route)
require __DIR__.'/install.php';

// Public memorial - deep links for tribute/chapter (MUST be before single-slug route)
Route::get('/{memorial_slug}/tribute/{share_id}', [PublicMemorialController::class, 'showTribute'])->name('memorial.tribute.public')->where(['memorial_slug' => '[a-z0-9\-]+', 'share_id' => '[a-z0-9]{7}']);
Route::get('/{memorial_slug}/chapter/{share_id}', [PublicMemorialController::class, 'showChapter'])->name('memorial.chapter.public')->where(['memorial_slug' => '[a-z0-9\-]+', 'share_id' => '[a-z0-9]{7}']);

// Public memorial by profile slug (e.g. /miiro-rio-akram) - MUST be last to avoid matching /login, /dashboard, etc.
Route::get('/{slug}', [PublicMemorialController::class, 'show'])->name('memorial.public')->where('slug', '[a-z0-9\-]+');
