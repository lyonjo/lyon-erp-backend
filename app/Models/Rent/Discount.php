<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $table = 'discounts';
    protected $fillable = ['contract_id', 'transportation_id', 'amount', 'is_approved', 'approved_by', 'created_by', 'note', 'company_name'];
    protected $casts = ['amount' => 'decimal:2', 'approved_by' => 'array', 'is_approved' => 'boolean'];
    public function contract()
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
}
