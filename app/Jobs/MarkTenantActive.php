<?php

namespace App\Jobs;

use Stancl\Tenancy\Contracts\TenantWithDatabase;

class MarkTenantActive
{
    public function __construct(protected TenantWithDatabase $tenant) {}

    public function handle(): void
    {
        $this->tenant->update(['status' => 'active']);

        // later: call the Store's "tenant is ready" webhook here
    }
}
