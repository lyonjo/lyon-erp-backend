<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Company extends Model
{
    protected $fillable = [
        'name',
        'location',
        'tax_number',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function logo(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')
            ->where('type', 'logo');
    }
}
