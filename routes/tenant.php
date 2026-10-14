<?php

declare(strict_types=1);

use App\Http\Controllers\Rent\VehicleController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Middleware\InitializeTenancyByHeader;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rent\CustomerController;
use App\Http\Controllers\Rent\CustomerCompanyController;


Route::middleware(['api', InitializeTenancyByHeader::class])
    ->prefix('api/t')
    ->group(function () {
        Route::get('/settings', [SettingsController::class, 'show']);

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);

            // Rent product
            Route::middleware(['module:rent', \App\Http\Middleware\NoCache::class])->prefix('rent')->group(function () {
                Route::get('customers', [CustomerController::class, 'index']);
                Route::get('/customers/{customer}', [CustomerController::class, 'show']);
                Route::post('/customers', [CustomerController::class, 'store']);
                Route::put('/customers/{customer}', [CustomerController::class, 'update']);
                Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
                Route::get('/vehicles', [VehicleController::class, 'index']);
                Route::post('/vehicles', [VehicleController::class, 'store']);
                Route::get('/customer-companies', [CustomerCompanyController::class, 'index']);
                Route::get('/customer-companies/{customerCompany}', [CustomerCompanyController::class, 'show']);
                Route::post('/customer-companies', [CustomerCompanyController::class, 'store']);
                Route::put('/customer-companies/{customerCompany}', [CustomerCompanyController::class, 'update']);
                Route::delete('/customer-companies/{customerCompany}', [CustomerCompanyController::class, 'destroy']);
            });
        });
    });
