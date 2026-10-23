<?php
namespace App\Models\Rent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CarReservation extends Model {
 protected $fillable=['booking_id','car_id','pickup_start_datetime','dropoff_end_datetime'];
 public function booking():BelongsTo{return $this->belongsTo(Booking::class);}
 public function car():BelongsTo{return $this->belongsTo(Vehicle::class,'car_id');}
}
