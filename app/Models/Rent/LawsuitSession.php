<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawsuitSession extends Model
{
    protected $fillable = ['lawsuit_id', 'date', 'note'];
    protected $casts = ['date' => 'date:Y-m-d'];
    public function lawsuit(): BelongsTo
    {
        return $this->belongsTo(Lawsuit::class);
    }
}
