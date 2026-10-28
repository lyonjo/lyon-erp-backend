<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class CustomerCompanyAttachment extends Model
{
    protected $fillable = ['customer_company_id', 'original_name', 'path', 'mime_type', 'size'];
}
