<?php
namespace App\Models\Rent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany,MorphMany};
class CarRentalPrice extends Model {
 protected $fillable=['brand_id','model_id','model_year','client_type','company_id'];
 protected $casts=['model_year'=>'integer'];
 public function brand():BelongsTo{return $this->belongsTo(VehicleBrand::class,'brand_id');}
 public function model():BelongsTo{return $this->belongsTo(VehicleModel::class,'model_id');}
 public function company():BelongsTo{return $this->belongsTo(CustomerCompany::class,'company_id');}
 public function periods():HasMany{return $this->hasMany(PricingPeriod::class,'car_rental_price_id');}
 public function images():MorphMany{return $this->morphMany(Image::class,'imageable');}
}
