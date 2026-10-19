<?php
namespace App\Models\Rent;
use Illuminate\Database\Eloquent\Model;
class VehicleBrand extends Model {
 protected $table='brands';protected $fillable=['name'];
 public function models(){return $this->hasMany(VehicleModel::class,'brand_id');}
 public function vehicles(){return $this->hasMany(Vehicle::class,'brand_id');}
}
