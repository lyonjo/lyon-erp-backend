<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class RentalContract extends Model
{
    protected $table = 'contracts';
    protected $fillable = ['booking_id', 'km_out', 'km_in', 'status'];
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
    public function additionalDrivers(): HasMany
    {
        return $this->hasMany(ContractAdditionalDriver::class, 'contract_id');
    }
}
