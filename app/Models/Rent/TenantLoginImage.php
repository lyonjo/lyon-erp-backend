<?php

declare(strict_types=1);

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class TenantLoginImage extends Model
{
    protected $table = 'tenant_login_images';

    protected $fillable = ['slot', 'path'];
}
