<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\MemorialApiController;
use App\Http\Controllers\MemorialController;
use App\Http\Controllers\MemorialDirectoryController;
use App\Http\Controllers\MemorialMediaController;
use App\Http\Controllers\MemorialSignupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicMemorialController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Auth routes (login, register, password reset, etc.)
require __DIR__.'/auth.php';

// Landing page (public)
Route::get('/', function () {
    return view('pages.landing', ['title' => 'Home']);
})->name('home');

// AJAX memorial search (public)
Route::get('/api/search/memorials', [MemorialController::class, 'search'])->name('memorials.search');

// Find Memorial directory (public)
Route::get('/find-memorial', [MemorialDirectoryController::class, 'index'])->name('memorial.directory');

// Memorial creation flow (multi-step signup)
Route::prefix('create-memorial')->name('memorial.create.')->group(function () {
    Route::get('/step-1', [MemorialSignupController::class, 'step1'])->name('step1');
    Route::post('/step-1', [MemorialSignupController::class, 'storeStep1'])->name('storeStep1');
    Route::get('/step-2', [MemorialSignupController::class, 'step2'])->name('step2');
    Route::post('/step-2/register', [MemorialSignupController::class, 'storeStep2Register'])->name('storeStep2Register');
    Route::post('/step-2/login', [MemorialSignupController::class, 'storeStep2Login'])->name('storeStep2Login');
    Route::post('/check-email', [MemorialSignupController::class, 'checkEmail'])->name('checkEmail');
    Route::get('/step-3', [MemorialSignupController::class, 'step3'])->name('step3');
    Route::post('/step-3', [MemorialSignupController::class, 'storeStep3'])->name('storeStep3');
    Route::post('/prepare-paid-checkout', [MemorialSignupController::class, 'preparePaidCheckout'])->name('preparePaidCheckout');
    Route::get('/complete', [MemorialSignupController::class, 'complete'])->name('complete');
    Route::get('/preparing/{slug}', [MemorialSignupController::class, 'preparing'])->name('preparing');
});

// Dashboard routes (protected)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::post('memorials/{memorial}/status', [MemorialController::class, 'updateStatus'])->name('memorials.status');
    Route::patch('memorials/{memorial}/section', [MemorialController::class, 'updateSection'])->name('memorials.section');
    Route::patch('memorials/{memorial}/fields', [MemorialController::class, 'updateFields'])->name('memorials.fields');
    Route::post('memorials/{memorial}/generate-biography', [MemorialController::class, 'generateBiography'])->name('memorials.generate-biography');
    Route::post('memorials/{memorial}/generate-template-biography', [MemorialController::class, 'generateTemplateBiography'])->name('memorials.generate-template-biography');
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
Route::post('/notifications/push/test', [NotificationController::class, 'testPush'])->name('notifications.push.test');

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
Route::get('/calendar', function () {
    return view('pages.calender', ['title' => 'Calendar']);
})->name('calendar');

// Blank page
Route::get('/blank', function () {
    return view('pages.blank', ['title' => 'Blank']);
})->name('blank');

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

    Route::get('/ai', [SettingsController::class, 'ai'])->name('ai');
    Route::put('/ai', [SettingsController::class, 'updateAi'])->name('ai.update');

    Route::get('/permissions', [SettingsController::class, 'permissions'])->name('permissions');
    Route::post('/permissions/roles', [SettingsController::class, 'storeRole'])->name('roles.store');
    Route::put('/permissions/users/{user}/role', [SettingsController::class, 'updateUserRole'])->name('users.role');
    Route::delete('/permissions/roles/{role}', [SettingsController::class, 'destroyRole'])->name('roles.destroy');

    Route::get('/payments', [SettingsController::class, 'payments'])->name('payments');
    Route::put('/payments', [SettingsController::class, 'updatePayments'])->name('payments.update');

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

    Route::get('/updates', [SettingsController::class, 'updates'])->name('updates');
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

// Payment callback & IPN (no auth - Pesapal redirects/IPN calls)
Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::get('/payment/complete', [PaymentController::class, 'complete'])->name('payment.complete');
Route::match(['get', 'post'], '/payment/ipn', [PaymentController::class, 'ipn'])->name('payment.ipn');

// Public memorial - deep links for tribute/chapter (MUST be before single-slug route)
Route::get('/{memorial_slug}/tribute/{share_id}', [PublicMemorialController::class, 'showTribute'])->name('memorial.tribute.public')->where(['memorial_slug' => '[a-z0-9\-]+', 'share_id' => '[a-z0-9]{7}']);
Route::get('/{memorial_slug}/chapter/{share_id}', [PublicMemorialController::class, 'showChapter'])->name('memorial.chapter.public')->where(['memorial_slug' => '[a-z0-9\-]+', 'share_id' => '[a-z0-9]{7}']);

// Public memorial by profile slug (e.g. /miiro-rio-akram) - MUST be last to avoid matching /login, /dashboard, etc.
Route::get('/{slug}', [PublicMemorialController::class, 'show'])->name('memorial.public')->where('slug', '[a-z0-9\-]+');
Route::post('/{slug}/tribute', [PublicMemorialController::class, 'storeTribute'])->name('memorial.tribute.store')->where('slug', '[a-z0-9\-]+');




















