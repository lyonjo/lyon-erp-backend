<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CustomerCompany extends Model
{
    protected $table = 'customer_companies';

    protected $fillable = [
        'name',
        'email',
        'country_code',
        'phone',
        'address',
        'is_blacklist',
        'is_multi_contract',
        'user_id',
    ];

    protected $casts = [
        'is_blacklist'      => 'boolean',
        'is_multi_contract' => 'boolean',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
