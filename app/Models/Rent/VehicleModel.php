<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class VehicleModel extends Model
{
    protected $table = 'models';
    protected $fillable = ['brand_id', 'name'];
    public function brand()
    {
        return $this->belongsTo(VehicleBrand::class, 'brand_id');
    }
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'model_id');
    }
}
