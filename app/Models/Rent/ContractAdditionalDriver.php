<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAdditionalDriver extends Model
{
    protected $table = 'additional_drivers';
    protected $fillable = ['contract_id', 'name', 'license_number', 'date_of_issue', 'expiry_date', 'place_of_issue'];
    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
}
