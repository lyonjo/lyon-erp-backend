<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExtraMoneyService extends Model
{
    protected $fillable = ['name', 'price', 'has_lawsuit'];
    protected $casts = ['price' => 'decimal:2', 'has_lawsuit' => 'boolean'];
    public function extraMoney(): BelongsToMany
    {
        return $this->belongsToMany(ExtraMoney::class, 'extra_money_extra_money_service')->withPivot('price');
    }
}
