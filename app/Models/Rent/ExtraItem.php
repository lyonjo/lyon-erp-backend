<?php

declare(strict_types=1);

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class ExtraItem extends Model
{
    protected $table = 'extra_items';

    protected $fillable = ['name', 'price', 'is_daily'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_daily' => 'boolean',
    ];
}
