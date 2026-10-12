<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Store\TenantProvisionController;

/*
|--------------------------------------------------------------------------
| Central API (Store -> Product app)
|--------------------------------------------------------------------------
| Only the Store backend calls these routes (server to server).
| Final URLs look like: /api/tenants, /api/tenants/{id}/suspend, ...
|
| Tenant routes (login, me, settings, ...) are NOT here.
| They live in routes/tenant.php under /api/t
*/

Route::prefix('tenants')->group(function () {
    // Added later, once the store.auth (HMAC/token) middleware exists:
    //
    // Route::middleware('store.auth')->group(function () {
    //     Route::post('/', [TenantController::class, 'store']);
    //     Route::get('/{id}', [TenantController::class, 'show']);
    //     Route::post('/{id}/suspend', [TenantController::class, 'suspend']);
    //     Route::post('/{id}/activate', [TenantController::class, 'activate']);
    //     Route::patch('/{id}/plan', [TenantController::class, 'updatePlan']);
    //     Route::delete('/{id}', [TenantController::class, 'destroy']);
    // });
});
