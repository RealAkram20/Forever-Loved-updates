<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', fn () => redirect()->route('install.requirements'));
    Route::get('/requirements', [InstallController::class, 'requirements'])->name('requirements');
    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database/validate', [InstallController::class, 'validateDatabase'])->name('database.validate');
    Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');
    Route::get('/settings', [InstallController::class, 'appSettings'])->name('settings');
    Route::post('/settings', [InstallController::class, 'storeAppSettings'])->name('settings.store');
    Route::get('/admin', [InstallController::class, 'adminAccount'])->name('admin');
    Route::post('/admin', [InstallController::class, 'storeAdmin'])->name('admin.store');
    Route::get('/run', [InstallController::class, 'run'])->name('run');
    Route::post('/execute/{step}', [InstallController::class, 'executeStep'])->name('execute.step')->where('step', '[1-9]');
    Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
});
