<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TrafficViolation extends Model
{
    protected $table = 'traffic_violations';
    protected $fillable = ['car_id', 'contract_id', 'driver_id', 'violation_date', 'violation_time', 'violation_no', 'violation_value', 'violation_reason', 'manual_customer_name'];
    protected $casts = ['violation_date' => 'date:Y-m-d', 'violation_value' => 'decimal:2'];

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
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'traffic_violation');
    }
}
