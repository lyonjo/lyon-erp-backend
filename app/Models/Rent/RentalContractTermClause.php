<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalContractTermClause extends Model
{
    protected $table = 'rental_contract_term_clauses';
    protected $fillable = ['rental_contract_terms_id', 'sort_order', 'text_en', 'text_ar'];

    public function terms(): BelongsTo
    {
        return $this->belongsTo(RentalContractTerms::class, 'rental_contract_terms_id');
    }
}
