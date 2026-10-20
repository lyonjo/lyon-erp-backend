<?php
namespace App\Models\Rent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PriceInterval extends Model {
 protected $fillable=['pricing_period_id','start','end','price'];
 protected $casts=['start'=>'integer','end'=>'integer','price'=>'decimal:2'];
 public function period():BelongsTo{return $this->belongsTo(PricingPeriod::class,'pricing_period_id');}
}
