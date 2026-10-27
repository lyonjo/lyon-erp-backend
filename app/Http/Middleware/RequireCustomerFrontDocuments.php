<?php

namespace App\Http\Middleware;

use App\Models\Rent\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Enforce required ID/license front photos for both new and existing customers.
 * Attach to the existing POST /customers and POST|PUT /customers/{customer} routes.
 */
class RequireCustomerFrontDocuments
{
    public function handle(Request $request, Closure $next)
    {
        $rawId = $request->route('customer') ?? $request->route('id');
        $customer = $rawId instanceof Customer ? $rawId : ($rawId ? Customer::findOrFail($rawId) : null);
        $errors = [];
        foreach (['id_front', 'license_front'] as $type) {
            $uploaded = $request->hasFile($type) && $request->file($type)->isValid();
            $existing = $customer && $customer->images()->where('type', $type)->exists();
            if (!$uploaded && !$existing) {
                $errors[$type] = [ucwords(str_replace('_', ' ', $type)) . ' photo is required.'];
            }
        }
        if ($errors) throw ValidationException::withMessages($errors);
        return $next($request);
    }
}
