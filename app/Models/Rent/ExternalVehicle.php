<?php

declare(strict_types=1);

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalVehicle extends Model
{
    protected $table = 'external_vehicles';

    protected $fillable = [
        'number', 'brand_id', 'model_id', 'model_year', 'supplier_id', 'branch_id',
        'vin_number', 'engine_number', 'registration_number', 'fuel_type', 'engine_size',
        'passenger_number', 'gearbox_type', 'camera', 'bluetooth',
        'license_expiration', 'insurance_expiration', 'current_km', 'odometer',
        'status', 'current_location',
    ];

    protected $casts = [
        'model_year' => 'integer',
        'passenger_number' => 'integer',
        'camera' => 'boolean',
        'bluetooth' => 'boolean',
        'status' => 'boolean',
        'current_km' => 'integer',
        'odometer' => 'integer',
        'license_expiration' => 'date:Y-m-d',
        'insurance_expiration' => 'date:Y-m-d',
    ];

    public function brand(): BelongsTo { return $this->belongsTo(VehicleBrand::class, 'brand_id'); }
    public function model(): BelongsTo { return $this->belongsTo(VehicleModel::class, 'model_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class, 'supplier_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class, 'branch_id'); }
}
