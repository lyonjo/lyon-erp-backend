<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractVideo extends Model
{
    protected $fillable = ['contract_id', 'path', 'original_name', 'mime_type', 'size_bytes'];
    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
}
