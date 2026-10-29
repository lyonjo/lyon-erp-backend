<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCompany extends Model
{
    protected $table = 'customer_companies';

    protected $fillable = [
        'name',
        'email',
        'admin_name',
        'country_code',
        'phone',
        'address',
        'is_blacklist',
        'note',
        'is_multi_contract',
        'user_id',
    ];

    protected $casts = [
        'is_blacklist' => 'boolean',
        'is_multi_contract' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerCompanyAttachment::class, 'customer_company_id');
    }
}
