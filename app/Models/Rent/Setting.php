<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'booking_tax_percent' => '0',
        'car_rental_km_daily' => '0',
        'car_rental_price_per_km' => '0',
        'currency' => 'JOD',
        'additional_hours' => '0',
    ];
}
