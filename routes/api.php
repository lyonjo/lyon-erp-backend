<?php

use App\Http\Controllers\Store\TenantProvisionController;
use Illuminate\Support\Facades\Route;

/*
| Central API: called only by the Store (server to server), signed with HMAC.
| Tenant routes (login, me, ...) live in routes/tenant.php under /api/t
*/

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
