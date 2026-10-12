<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module)
    {
        if (! in_array($module, tenant()->activeModuleKeys(), true)) {
            return response()->json(['message' => 'This product is not available.'], 403);
        }

        return $next($request);
    }
}
