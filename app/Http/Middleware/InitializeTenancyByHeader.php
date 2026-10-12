<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;

class InitializeTenancyByHeader
{
    public function __construct(
        protected Tenancy $tenancy,
        protected DomainTenantResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $domain = $request->header('X-Tenant-Domain');

        if (! $domain) {
            return response()->json(['message' => 'Missing X-Tenant-Domain header.'], 400);
        }

        try {
            $tenant = $this->resolver->resolve($domain);
        } catch (TenantCouldNotBeIdentifiedOnDomainException $e) {
            return response()->json(['message' => 'Unknown tenant domain.'], 404);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
