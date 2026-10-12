<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyStoreSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('services.store.secret');
        $ts     = $request->header('X-Timestamp');
        $sig    = $request->header('X-Signature');

        if (! $secret || ! $ts || ! $sig || abs(time() - (int) $ts) > 300) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $expected = hash_hmac('sha256', $ts . '.' . $request->getContent(), $secret);

        if (! hash_equals($expected, $sig)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
