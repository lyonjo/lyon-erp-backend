<?php

declare(strict_types=1);

namespace App\Models\Rent;

use App\Enums\MaintenanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends Model
{
    protected $table = 'vehicle_maintenances';

    protected $fillable = ['car_id', 'service_key', 'price', 'details', 'counter_km', 'date'];

    protected $casts = [
        'service_key' => MaintenanceService::class,
        'price' => 'decimal:2',
        'counter_km' => 'integer',
        'date' => 'date:Y-m-d',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'car_id');
    }
}
