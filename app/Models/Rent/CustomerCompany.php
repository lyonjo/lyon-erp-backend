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
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
