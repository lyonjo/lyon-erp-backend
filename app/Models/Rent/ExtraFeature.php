<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ExtraFeature extends Model
{
    protected $table = 'extra_features';

    protected $fillable = [
        'name',
    ];

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(
            Vehicle::class,
            'car_features',
            'feature_id',
            'car_id'
        );
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
