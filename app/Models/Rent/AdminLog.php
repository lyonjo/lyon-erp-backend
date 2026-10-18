<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AdminLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model',
        'model_id',
        'description',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
