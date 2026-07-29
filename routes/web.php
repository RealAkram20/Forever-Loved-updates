<?php

use App\Http\Controllers\Admin\ResellerController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MemorialApiController;
use App\Http\Controllers\MemorialController;
use App\Http\Controllers\MemorialDirectoryController;
use App\Http\Controllers\MemorialMediaController;
use App\Http\Controllers\MemorialSignupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicMemorialController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WidgetController;
use App\Http\Middleware\EmbedFrameHeaders;
use App\Http\Middleware\EnsureResellerActive;
use App\Http\Middleware\ResolveReseller;
use App\Http\Middleware\ResolveResellerByCustomDomain;
use Illuminate\Support\Facades\Route;

// Auth routes (login, register, password reset, etc.)
require __DIR__.'/auth.php';

// Landing / Home page (public)
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
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->middleware(EnsureResellerActive::class)->name('dashboard');

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

    // Calendar
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::post('/calendar/events', [CalendarController::class, 'store'])->name('calendar.events.store');
    Route::put('/calendar/events/{calendarEvent}', [CalendarController::class, 'update'])->name('calendar.events.update');
    Route::delete('/calendar/events/{calendarEvent}', [CalendarController::class, 'destroy'])->name('calendar.events.destroy');

    // ─── Users Management (admin only) ──────────────────────────────
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    // Admin push onboarding dismiss
    Route::post('/admin/dismiss-push-onboarding', function (\Illuminate\Http\Request $request) {
        $request->session()->put('admin_push_onboarding_dismissed', true);

        return response()->json(['success' => true]);
    })->middleware(['auth', 'role:admin|super-admin'])->name('admin.dismiss-push-onboarding');

    // ─── Admin Settings ──────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [SettingsController::class, 'general'])->name('general');
        Route::put('/general', [SettingsController::class, 'updateGeneral'])->name('general.update');

        Route::get('/appearance', [\App\Http\Controllers\Admin\AppearanceController::class, 'index'])->name('appearance');
        Route::put('/appearance', [\App\Http\Controllers\Admin\AppearanceController::class, 'update'])->name('appearance.update');
        Route::post('/appearance/fonts', [\App\Http\Controllers\Admin\AppearanceController::class, 'storeFont'])->name('appearance.fonts.store');
        Route::delete('/appearance/fonts/{index}', [\App\Http\Controllers\Admin\AppearanceController::class, 'destroyFont'])->whereNumber('index')->name('appearance.fonts.destroy');

        Route::get('/ai', [SettingsController::class, 'ai'])->name('ai');
        Route::put('/ai', [SettingsController::class, 'updateAi'])->name('ai.update');

        Route::get('/permissions', [SettingsController::class, 'permissions'])->name('permissions');
        Route::post('/permissions/roles', [SettingsController::class, 'storeRole'])->name('roles.store');
        Route::put('/permissions/users/{user}/role', [SettingsController::class, 'updateUserRole'])->name('users.role');
        Route::delete('/permissions/roles/{role}', [SettingsController::class, 'destroyRole'])->name('roles.destroy');

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

        Route::get('/menus', [\App\Http\Controllers\Admin\MenuController::class, 'edit'])->name('menus.edit');
        Route::post('/menus/items', [\App\Http\Controllers\Admin\MenuController::class, 'storeItem'])->name('menus.items.store');
        Route::put('/menus/items/{item}', [\App\Http\Controllers\Admin\MenuController::class, 'updateItem'])->name('menus.items.update');
        Route::delete('/menus/items/{item}', [\App\Http\Controllers\Admin\MenuController::class, 'destroyItem'])->name('menus.items.destroy');
        Route::post('/menus/reorder', [\App\Http\Controllers\Admin\MenuController::class, 'reorder'])->name('menus.reorder');

        Route::get('/site-layout/{key}/edit', [\App\Http\Controllers\Admin\SiteLayoutController::class, 'edit'])->name('site-layout.edit');
        Route::put('/site-layout/{key}', [\App\Http\Controllers\Admin\SiteLayoutController::class, 'update'])->name('site-layout.update');

        // Editable Pages (route-based SEO lives under /pages/seo/..., not a separate Settings section)
        Route::get('/pages', [\App\Http\Controllers\Admin\PageController::class, 'index'])->name('pages.index');
        Route::get('/pages/create', [\App\Http\Controllers\Admin\PageController::class, 'create'])->name('pages.create');
        Route::post('/pages', [\App\Http\Controllers\Admin\PageController::class, 'store'])->name('pages.store');
        Route::post('/pages/preview', [\App\Http\Controllers\Admin\PageController::class, 'preview'])->name('pages.preview');
        Route::get('/pages/seo/{routeKey}/edit', [\App\Http\Controllers\Admin\PageController::class, 'editSeoRoute'])->name('pages.seo.edit')->where('routeKey', '[A-Za-z0-9._\-]+');
        Route::put('/pages/seo/{routeKey}', [\App\Http\Controllers\Admin\PageController::class, 'updateSeoRoute'])->name('pages.seo.update')->where('routeKey', '[A-Za-z0-9._\-]+');
        Route::get('/pages/{slug}/layout', [\App\Http\Controllers\Admin\PageController::class, 'editLayout'])->name('pages.layout.edit');
        Route::put('/pages/{slug}/layout', [\App\Http\Controllers\Admin\PageController::class, 'updateLayout'])->name('pages.layout.update');
        Route::post('/pages/{slug}/meta', [\App\Http\Controllers\Admin\PageController::class, 'updatePageMeta'])->name('pages.meta.update');
        Route::get('/pages/{slug}/edit', [\App\Http\Controllers\Admin\PageController::class, 'edit'])->name('pages.edit');
        Route::delete('/pages/{slug}', [\App\Http\Controllers\Admin\PageController::class, 'destroy'])->name('pages.destroy');

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
        Route::post('/resellers/{reseller}/restore', [ResellerController::class, 'restore'])->name('resellers.restore');
        Route::post('/resellers/{reseller}/login-as', [ResellerController::class, 'loginAs'])->name('resellers.login-as');
        Route::post('/resellers/{reseller}/payments', [ResellerController::class, 'recordPayment'])->name('resellers.payments.store');

        Route::get('/reseller-pricing', [\App\Http\Controllers\Admin\ResellerTierController::class, 'index'])->name('reseller-pricing');
        Route::post('/reseller-tiers', [\App\Http\Controllers\Admin\ResellerTierController::class, 'store'])->name('reseller-tiers.store');
        Route::put('/reseller-tiers/{resellerTier}', [\App\Http\Controllers\Admin\ResellerTierController::class, 'update'])->name('reseller-tiers.update');
        Route::delete('/reseller-tiers/{resellerTier}', [\App\Http\Controllers\Admin\ResellerTierController::class, 'destroy'])->name('reseller-tiers.destroy');

        Route::get('/reseller-settings', [\App\Http\Controllers\Admin\ResellerSettingsController::class, 'edit'])->name('reseller-settings');
        Route::put('/reseller-settings', [\App\Http\Controllers\Admin\ResellerSettingsController::class, 'update'])->name('reseller-settings.update');
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
        Route::get('/memorials', [\App\Http\Controllers\Reseller\DashboardController::class, 'memorials'])->name('memorials');
        Route::get('/memorials/create', [\App\Http\Controllers\Reseller\DashboardController::class, 'createMemorial'])->name('memorials.create');
        Route::post('/memorials', [\App\Http\Controllers\Reseller\DashboardController::class, 'storeMemorial'])->name('memorials.store');

        Route::get('/analytics', [\App\Http\Controllers\Reseller\AnalyticsController::class, 'index'])->name('analytics');

        Route::get('/clients', [\App\Http\Controllers\Reseller\ClientController::class, 'index'])->name('clients');
        Route::post('/clients', [\App\Http\Controllers\Reseller\ClientController::class, 'store'])->name('clients.store');
        Route::put('/clients/{user}', [\App\Http\Controllers\Reseller\ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{user}', [\App\Http\Controllers\Reseller\ClientController::class, 'destroy'])->name('clients.destroy');

        Route::get('/plans', [\App\Http\Controllers\Reseller\PlanController::class, 'index'])->name('plans');
        Route::post('/plans', [\App\Http\Controllers\Reseller\PlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{plan}', [\App\Http\Controllers\Reseller\PlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [\App\Http\Controllers\Reseller\PlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('/settings', [\App\Http\Controllers\Reseller\SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings', [\App\Http\Controllers\Reseller\SettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/domain', [\App\Http\Controllers\Reseller\SettingsController::class, 'updateCustomDomain'])->name('settings.domain.update');
        Route::post('/settings/domain/verify', [\App\Http\Controllers\Reseller\SettingsController::class, 'verifyCustomDomain'])->name('settings.domain.verify');

        Route::get('/branding', [\App\Http\Controllers\Reseller\BrandingController::class, 'edit'])->name('branding');
        Route::put('/branding', [\App\Http\Controllers\Reseller\BrandingController::class, 'update'])->name('branding.update');

        Route::get('/payments', [\App\Http\Controllers\Reseller\PaymentSettingsController::class, 'edit'])->name('payments');
        Route::put('/payments', [\App\Http\Controllers\Reseller\PaymentSettingsController::class, 'update'])->name('payments.update');
        Route::post('/payments/register-ipn', [\App\Http\Controllers\Reseller\PaymentSettingsController::class, 'registerIpn'])->name('payments.register-ipn');
    });
});

// Memorial API (AJAX - no page reload)
Route::prefix('m/{slug}')->where(['slug' => '[a-z0-9\-]+'])->name('memorial.api.')->group(function () {
    Route::patch('/section', [MemorialApiController::class, 'updateSection'])->name('section');
    Route::post('/tribute', [MemorialApiController::class, 'storeTribute'])->name('tribute');
    Route::post('/track-share', [MemorialApiController::class, 'trackShare'])->name('track-share');
    Route::get('/stats', [MemorialApiController::class, 'stats'])->name('stats');
    Route::post('/reaction', [MemorialApiController::class, 'storeReaction'])->name('reaction');
    Route::get('/posts', [MemorialApiController::class, 'posts'])->name('posts');
    Route::post('/posts', [MemorialApiController::class, 'storePost'])->name('posts.store');
    Route::patch('/posts/{postId}', [MemorialApiController::class, 'updatePost'])->name('posts.update');
    Route::delete('/posts/{postId}', [MemorialApiController::class, 'deletePost'])->name('posts.delete');
    Route::get('/posts/{postId}/comments', [MemorialApiController::class, 'comments'])->name('posts.comments');
    Route::post('/posts/{postId}/comments', [MemorialApiController::class, 'storeComment'])->name('posts.comments.store');
    Route::delete('/comments/{commentId}', [MemorialApiController::class, 'deleteComment'])->name('comments.delete');
    Route::get('/posts/{postId}/reactions', [MemorialApiController::class, 'reactions'])->name('posts.reactions');
    Route::get('/tributes', [MemorialApiController::class, 'tributes'])->name('tributes');
    Route::post('/tributes/{tributeId}/comments', [MemorialApiController::class, 'storeTributeComment'])->name('tributes.comments.store');
    Route::delete('/tribute-comments/{commentId}', [MemorialApiController::class, 'deleteTributeComment'])->name('tributes.comments.delete');
    Route::patch('/tributes/{tributeId}', [MemorialApiController::class, 'updateTribute'])->name('tributes.update');
    Route::delete('/tributes/{tributeId}', [MemorialApiController::class, 'deleteTribute'])->name('tributes.delete');
    Route::get('/chapters', [MemorialApiController::class, 'chapters'])->name('chapters');
    Route::post('/chapters', [MemorialApiController::class, 'storeChapter'])->name('chapters.store');
    Route::patch('/chapters/{chapterId}', [MemorialApiController::class, 'updateChapter'])->name('chapters.update');
    Route::delete('/chapters/{chapterId}', [MemorialApiController::class, 'deleteChapter'])->name('chapters.delete');
    // Memorial subscriptions
    Route::post('/subscribe', [MemorialApiController::class, 'subscribe'])->name('subscribe');
    Route::put('/subscribe', [MemorialApiController::class, 'updateSubscription'])->name('subscribe.update');
    Route::delete('/subscribe', [MemorialApiController::class, 'unsubscribe'])->name('subscribe.delete');
    Route::get('/subscribe/check', [MemorialApiController::class, 'checkSubscription'])->name('subscribe.check');
    // Media uploads
    Route::post('/profile-photo', [MemorialMediaController::class, 'uploadProfilePhoto'])->name('profile-photo');
    Route::post('/gallery', [MemorialMediaController::class, 'uploadGalleryMedia'])->name('gallery');
    Route::patch('/gallery/{mediaId}', [MemorialMediaController::class, 'updateGalleryMedia'])->name('gallery.update');
    Route::delete('/gallery/{mediaId}', [MemorialMediaController::class, 'deleteGalleryMedia'])->name('gallery.delete');
    Route::post('/post-media', [MemorialMediaController::class, 'uploadPostMedia'])->name('post-media');
    Route::post('/tribute-post', [MemorialMediaController::class, 'storeTributePost'])->name('tribute-post');
    Route::post('/background-music', [MemorialMediaController::class, 'uploadBackgroundMusic'])->name('background-music');
    Route::delete('/background-music', [MemorialMediaController::class, 'removeBackgroundMusic'])->name('background-music.delete');
});

// Embed widget (public, unauthenticated) - read-only memorial view for iframe embedding
// on a reseller's own external site, via public/embed.js.
Route::get('/widget/{slug}', [WidgetController::class, 'show'])
    ->name('widget.show')
    ->where('slug', '[a-z0-9\-]+')
    ->middleware(EmbedFrameHeaders::class);

// Reseller white-labeled subdomain (e.g. acme.foreverloved.com) - public memorial pages
// only, scoped strictly to that reseller's own memorials. Matches on Host header, so it
// never competes with the apex catch-all route below.
Route::domain('{reseller}.'.config('reseller.domain'))->group(function () {
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
// The exclusion regex below is what actually keeps this scoped to genuinely foreign hosts.
$appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
$resellerBaseDomain = config('reseller.domain');
$foreignDomainPattern = '^(?!'.preg_quote($appHost, '#').'$)(?!'.preg_quote($resellerBaseDomain, '#').'$)(?!.*\.'.preg_quote($resellerBaseDomain, '#').'$).+$';

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
