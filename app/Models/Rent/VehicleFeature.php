<?php
namespace App\Models\Rent;
use Illuminate\Database\Eloquent\Model;
class VehicleFeature extends Model {
 protected $table='extra_features';protected $fillable=['name'];
 public function image(){return $this->morphOne(Image::class,'imageable')->where('type','feature');}
 public function vehicles(){return $this->belongsToMany(Vehicle::class,'car_features','feature_id','car_id');}
}
