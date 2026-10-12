<?php

namespace App\Jobs;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SyncTenantPermissions
{
    public function __construct(protected TenantWithDatabase $tenant) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $all = collect(config('module_permissions'))->flatten();

            foreach ($all as $name) {
                Permission::findOrCreate($name, 'web');
            }

            Role::findOrCreate('Admin', 'web')->syncPermissions($all);
        });
    }
}
