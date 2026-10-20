<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PricingPeriod extends Model
{
    protected $fillable = ['car_rental_price_id', 'start_date', 'end_date'];
    protected $casts = ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
    public function rentalPrice(): BelongsTo
    {
        return $this->belongsTo(CarRentalPrice::class, 'car_rental_price_id');
    }
    public function intervals(): HasMany
    {
        return $this->hasMany(PriceInterval::class, 'pricing_period_id');
    }
}
