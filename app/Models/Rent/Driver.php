<?php

declare(strict_types=1);

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $table = 'drivers';

    protected $fillable = ['name', 'license_number', 'phone', 'email', 'status'];

    protected $casts = ['status' => 'boolean'];
}
