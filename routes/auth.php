<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordlessLoginController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Passwordless login (email + code)
    Route::get('login/code', [PasswordlessLoginController::class, 'showEmailForm'])->name('login.passwordless');
    Route::post('login/code', [PasswordlessLoginController::class, 'sendCode'])->middleware('throttle:5,1')->name('login.code.send');
    Route::get('login/verify', [PasswordlessLoginController::class, 'showCodeForm'])->name('login.code');
    Route::post('login/verify', [PasswordlessLoginController::class, 'verifyCode'])->middleware('throttle:15,1')->name('login.code.verify');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    Route::get('auth/google', [GoogleLoginController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('google.redirect');

    Route::get('auth/google/callback', [GoogleLoginController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('google.callback');

    // Google One Tap: the corner prompt posts its credential here and the page reloads
    // signed in. Same guest gate as the other doors — a signed-in visitor has no prompt.
    Route::post('auth/google/one-tap', [GoogleLoginController::class, 'oneTap'])
        ->middleware('throttle:20,1')
        ->name('google.one-tap');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
