<?php

use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('location')->group(function () {
    Route::get('countries', [LocationController::class, 'countries']);
    Route::get('states/{countryCode}', [LocationController::class, 'states']);
    Route::get('cities/{stateId}', [LocationController::class, 'cities']);
});
