<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    protected $fillable = ['path', 'title', 'alt', 'type'];

    public function imageable()
    {
        return $this->morphTo();
    }

    /** Public URL of the file (needs `php artisan storage:link`). */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    /** Delete the file from disk and the row from the database. */
    public function deleteWithFile(): void
    {
        Storage::disk('public')->delete($this->path);
        $this->delete();
    }
}
