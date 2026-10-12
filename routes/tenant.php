<?php

declare(strict_types=1);

use App\Http\Controllers\Rent\VehicleController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Middleware\InitializeTenancyByHeader;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', InitializeTenancyByHeader::class])
    ->prefix('api/t')
    ->group(function () {
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);

            // Rent product
            Route::middleware('module:rent')->prefix('rent')->group(function () {
                Route::get('/vehicles', [VehicleController::class, 'index'])
                    ->middleware('can:rent.vehicles.view');
                Route::post('/vehicles', [VehicleController::class, 'store'])
                    ->middleware('can:rent.vehicles.manage');
            });
        });
    });
