<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalContractTerms extends Model
{
    protected $table = 'rental_contract_terms';
    protected $fillable = ['paragraph_en', 'paragraph_ar'];

    public function clauses(): HasMany
    {
        return $this->hasMany(RentalContractTermClause::class, 'rental_contract_terms_id')
            ->orderBy('sort_order');
    }
}
