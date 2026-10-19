<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vehicle extends Model
{
    protected $table = 'vehicles';

    protected $fillable = [
        'number',
        'brand_id',
        'model_id',
        'model_year',
        'company_id',
        'branch_id',
        'vin_number',
        'engine_number',
        'engine_size',
        'registration_number',
        'fuel_type',
        'passengers_number',
        'gearbox_type',
        'has_blutooth',
        'has_camera',
        'license_expiration',
        'insurance_expiration',
        'current_km',
        'odometer',
        'current_location',
        'status',
    ];

    protected $casts = [
        'model_year' => 'integer',
        'passengers_number' => 'integer',
        'current_km' => 'integer',
        'odometer' => 'integer',
        'has_blutooth' => 'boolean',
        'has_camera' => 'boolean',
        'status' => 'boolean',
        'license_expiration' => 'date:Y-m-d',
        'insurance_expiration' => 'date:Y-m-d',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class, 'brand_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(
            ExtraFeature::class,
            'car_features',
            'car_id',
            'feature_id'
        );
    }
}
