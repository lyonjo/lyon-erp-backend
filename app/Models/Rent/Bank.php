<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bank extends Model
{
    protected $fillable = [
        'name', 'company_id', 'account_id', 'for_sold_car',
        'for_lawsuits', 'for_companies', 'for_booking', 'for_contracts',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'account_id' => 'integer',
        'for_sold_car' => 'boolean',
        'for_lawsuits' => 'boolean',
        'for_companies' => 'boolean',
        'for_booking' => 'boolean',
        'for_contracts' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
