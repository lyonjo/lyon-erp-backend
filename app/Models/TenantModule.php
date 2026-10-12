<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantModule extends Model
{
    use CentralConnection;

    protected $fillable = ['tenant_id', 'module', 'plan', 'status', 'external_order_id', 'ends_at'];
    protected $casts = ['ends_at' => 'datetime'];

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
