<?php

declare(strict_types=1);

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoldCar extends Model
{
    protected $table = 'sold_cars';

    protected $fillable = ['vehicle_id', 'company_id', 'buyer_name', 'phone', 'price', 'tax'];

    protected $casts = ['price' => 'decimal:2', 'tax' => 'boolean'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
