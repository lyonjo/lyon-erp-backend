<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCompanyAttachment extends Model
{
    protected $fillable = ['customer_company_id', 'original_name', 'path', 'mime_type', 'size'];

    public function customerCompany(): BelongsTo
    {
        return $this->belongsTo(CustomerCompany::class, 'customer_company_id');
    }
}
