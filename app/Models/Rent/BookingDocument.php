<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDocument extends Model
{
    protected $fillable = ['booking_id', 'doc_type', 'path', 'original_name', 'mime_type'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
