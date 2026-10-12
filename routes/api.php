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
Route::middleware('store.auth')->prefix('tenants')->group(function () {
    Route::post('/provision', [TenantProvisionController::class, 'provision']);
    Route::get('/{tenant}', [TenantProvisionController::class, 'show']);
    Route::patch('/{tenant}/admin', [TenantProvisionController::class, 'updateAdmin']);
    Route::delete('/{tenant}', [TenantProvisionController::class, 'destroy']);

    Route::patch('/{tenant}/modules/{module}', [TenantProvisionController::class, 'updateModule']);
    Route::post('/{tenant}/modules/{module}/suspend', [TenantProvisionController::class, 'suspendModule']);
    Route::post('/{tenant}/modules/{module}/activate', [TenantProvisionController::class, 'activateModule']);
    Route::delete('/{tenant}/modules/{module}', [TenantProvisionController::class, 'destroyModule']);
});
