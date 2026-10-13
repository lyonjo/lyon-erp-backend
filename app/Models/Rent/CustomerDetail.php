<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDetail extends Model
{
    protected $fillable = [
        'customer_id',
        'nationality',
        'place_of_birth',
        'date_of_birth',
        'passport_place_of_issue',
        'passport_date_of_issue',
        'passport_date_of_expire',
        'license_no',
        'license_place_of_issue',
        'license_date_of_issue',
        'license_date_of_expire',
    ];

    protected $casts = [
        'date_of_birth'           => 'date',
        'passport_date_of_issue'  => 'date',
        'passport_date_of_expire' => 'date',
        'license_date_of_issue'   => 'date',
        'license_date_of_expire'  => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
