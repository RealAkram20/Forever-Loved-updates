<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers LaraUpdater routes with the correct controller namespace.
 * The package has a namespace case mismatch (laraUpdater vs laraupdater).
 * We disable the package's provider and use this one instead.
 */
class LaraUpdaterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(
            base_path('vendor/pcinaglia/laraupdater/lang'),
            'laraupdater'
        );

        Route::middleware(config('laraupdater.middleware', ['web', 'auth', 'role:admin|super-admin']))->group(function () {
            Route::get('updater.check', [\App\Http\Controllers\Admin\LaraUpdaterController::class, 'check']);
            Route::get('updater.currentVersion', [\App\Http\Controllers\Admin\LaraUpdaterController::class, 'getCurrentVersion']);
            Route::post('updater.update', [\App\Http\Controllers\Admin\LaraUpdaterController::class, 'update']);
        });
    }
}
