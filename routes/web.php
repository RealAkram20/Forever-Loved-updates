<?php

use App\Http\Controllers\MemorialApiController;
use App\Http\Controllers\MemorialController;
use App\Http\Controllers\MemorialMediaController;
use App\Http\Controllers\MemorialSignupController;
use App\Http\Controllers\PublicMemorialController;
use Illuminate\Support\Facades\Route;

// Auth routes (login, register, password reset, etc.)
require __DIR__.'/auth.php';

// Landing page (public)
Route::get('/', function () {
    return view('pages.landing', ['title' => 'Home']);
})->name('home');

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
    Route::get('/complete', [MemorialSignupController::class, 'complete'])->name('complete');
    Route::get('/preparing/{slug}', [MemorialSignupController::class, 'preparing'])->name('preparing');
});

// Dashboard routes (protected)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('pages.dashboard.ecommerce', ['title' => 'Dashboard']);
    })->name('dashboard');

    Route::post('memorials/{memorial}/status', [MemorialController::class, 'updateStatus'])->name('memorials.status');
    Route::patch('memorials/{memorial}/section', [MemorialController::class, 'updateSection'])->name('memorials.section');
    Route::patch('memorials/{memorial}/fields', [MemorialController::class, 'updateFields'])->name('memorials.fields');
    Route::post('memorials/{memorial}/generate-biography', [MemorialController::class, 'generateBiography'])->name('memorials.generate-biography');
    Route::post('memorials/{memorial}/generate-template-biography', [MemorialController::class, 'generateTemplateBiography'])->name('memorials.generate-template-biography');
    Route::patch('memorials/{memorial}/biography', [MemorialController::class, 'saveBiography'])->name('memorials.save-biography');
    Route::resource('memorials', MemorialController::class);

// calender pages
Route::get('/calendar', function () {
    return view('pages.calender', ['title' => 'Calendar']);
})->name('calendar');

// profile pages
Route::get('/profile', function () {
    return view('pages.profile', ['title' => 'Profile']);
})->name('profile');

// billing pages (user only)
Route::get('/billing/payments', function () {
    return view('pages.billing.payments', ['title' => 'Payments']);
})->name('billing.payments');
Route::get('/billing/subscription', function () {
    return view('pages.billing.subscription', ['title' => 'Subscription']);
})->name('billing.subscription');

// form pages
Route::get('/form-elements', function () {
    return view('pages.form.form-elements', ['title' => 'Form Elements']);
})->name('form-elements');

// tables pages
Route::get('/basic-tables', function () {
    return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
})->name('basic-tables');

// pages

Route::get('/blank', function () {
    return view('pages.blank', ['title' => 'Blank']);
})->name('blank');

// error pages
Route::get('/error-404', function () {
    return view('pages.errors.error-404', ['title' => 'Error 404']);
})->name('error-404');

// chart pages
Route::get('/line-chart', function () {
    return view('pages.chart.line-chart', ['title' => 'Line Chart']);
})->name('line-chart');

Route::get('/bar-chart', function () {
    return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
})->name('bar-chart');

// ui elements pages
Route::get('/alerts', function () {
    return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
})->name('alerts');

Route::get('/avatars', function () {
    return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
})->name('avatars');

Route::get('/badge', function () {
    return view('pages.ui-elements.badges', ['title' => 'Badges']);
})->name('badges');

Route::get('/buttons', function () {
    return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
})->name('buttons');

Route::get('/image', function () {
    return view('pages.ui-elements.images', ['title' => 'Images']);
})->name('images');

Route::get('/videos', function () {
    return view('pages.ui-elements.videos', ['title' => 'Videos']);
})->name('videos');
});

// Memorial API (AJAX - no page reload)
Route::prefix('m/{slug}')->where(['slug' => '[a-z0-9\-]+'])->name('memorial.api.')->group(function () {
    Route::patch('/section', [MemorialApiController::class, 'updateSection'])->name('section');
    Route::post('/tribute', [MemorialApiController::class, 'storeTribute'])->name('tribute');
    Route::post('/track-share', [MemorialApiController::class, 'trackShare'])->name('track-share');
    Route::post('/reaction', [MemorialApiController::class, 'storeReaction'])->name('reaction');
    Route::get('/posts', [MemorialApiController::class, 'posts'])->name('posts');
    Route::post('/posts', [MemorialApiController::class, 'storePost'])->name('posts.store');
    Route::patch('/posts/{postId}', [MemorialApiController::class, 'updatePost'])->name('posts.update');
    Route::delete('/posts/{postId}', [MemorialApiController::class, 'deletePost'])->name('posts.delete');
    Route::get('/posts/{postId}/comments', [MemorialApiController::class, 'comments'])->name('posts.comments');
    Route::post('/posts/{postId}/comments', [MemorialApiController::class, 'storeComment'])->name('posts.comments.store');
    Route::get('/posts/{postId}/reactions', [MemorialApiController::class, 'reactions'])->name('posts.reactions');
    Route::get('/tributes', [MemorialApiController::class, 'tributes'])->name('tributes');
    Route::post('/tributes/{tributeId}/comments', [MemorialApiController::class, 'storeTributeComment'])->name('tributes.comments.store');
    Route::get('/chapters', [MemorialApiController::class, 'chapters'])->name('chapters');
    Route::post('/chapters', [MemorialApiController::class, 'storeChapter'])->name('chapters.store');
    // Media uploads
    Route::post('/profile-photo', [MemorialMediaController::class, 'uploadProfilePhoto'])->name('profile-photo');
    Route::post('/gallery', [MemorialMediaController::class, 'uploadGalleryMedia'])->name('gallery');
    Route::post('/post-media', [MemorialMediaController::class, 'uploadPostMedia'])->name('post-media');
    Route::post('/tribute-post', [MemorialMediaController::class, 'storeTributePost'])->name('tribute-post');
});

// Public memorial - deep links for tribute/chapter (MUST be before single-slug route)
Route::get('/{memorial_slug}/tribute/{share_id}', [PublicMemorialController::class, 'showTribute'])->name('memorial.tribute.public')->where(['memorial_slug' => '[a-z0-9\-]+', 'share_id' => '[a-z0-9]{7}']);
Route::get('/{memorial_slug}/chapter/{share_id}', [PublicMemorialController::class, 'showChapter'])->name('memorial.chapter.public')->where(['memorial_slug' => '[a-z0-9\-]+', 'share_id' => '[a-z0-9]{7}']);

// Public memorial by profile slug (e.g. /miiro-rio-akram) - MUST be last to avoid matching /login, /dashboard, etc.
Route::get('/{slug}', [PublicMemorialController::class, 'show'])->name('memorial.public')->where('slug', '[a-z0-9\-]+');
Route::post('/{slug}/tribute', [PublicMemorialController::class, 'storeTribute'])->name('memorial.tribute.store')->where('slug', '[a-z0-9\-]+');






















