<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, MorphOne, HasMany};

class ExtraMoney extends Model
{
    protected $table = 'extra_money';
    protected $fillable = ['car_id', 'contract_id', 'driver_id', 'date', 'time', 'has_croka', 'is_responsible', 'croka_number', 'croka_amount', 'value', 'note', 'manual_customer_name'];
    protected $casts = ['date' => 'date:Y-m-d', 'value' => 'decimal:2', 'croka_amount' => 'decimal:2', 'has_croka' => 'boolean', 'is_responsible' => 'boolean'];
    public function car(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'car_id');
    }
    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ExtraMoneyService::class, 'extra_money_extra_money_service')->withPivot('price');
    }
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'extra_money');
    }
    public function lawsuits(): HasMany
    {
        return $this->hasMany(Lawsuit::class, 'extra_money_id');
    }
}
