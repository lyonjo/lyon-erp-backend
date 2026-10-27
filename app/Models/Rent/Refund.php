<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $table = 'refunds';
    protected $fillable = ['contract_id', 'transportation_id', 'amount', 'date', 'company_name'];
    protected $casts = ['amount' => 'decimal:2', 'date' => 'date:Y-m-d'];
    public function contract()
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
}
