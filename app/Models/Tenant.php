<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $casts = ['ends_at' => 'datetime'];

    public static function getCustomColumns(): array
    {
        return ['id', 'status', 'company_name', 'admin_name', 'admin_email', 'admin_phone'];
    }

    public function modules()
    {
        return $this->hasMany(TenantModule::class, 'tenant_id');
    }

    public function activeModuleKeys(): array
    {
        return $this->modules()->get()
            ->filter(fn($m) => $m->isActive())
            ->pluck('module')->values()->all();
    }
}
