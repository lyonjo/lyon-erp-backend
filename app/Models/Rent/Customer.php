<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use App\Enums\ClientSide;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Models\User;

class Customer extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'client_side',
        'country_code',
        'phone_number',
        'address',
        'is_multi_contract',
        'is_blacklist',
        'note',
    ];

    protected $casts = [
        'client_side'       => ClientSide::class,
        'is_multi_contract' => 'boolean',
        'is_blacklist'      => 'boolean',
        'date_of_birth'           => 'date:Y-m-d',
        'passport_date_of_issue'  => 'date:Y-m-d',
        'passport_date_of_expire' => 'date:Y-m-d',
        'license_date_of_issue'   => 'date:Y-m-d',
        'license_date_of_expire'  => 'date:Y-m-d',
    ];

    public const DOCUMENT_TYPES = ['id_front', 'id_back', 'license_front', 'license_back', 'passport'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasOne
    {
        return $this->hasOne(CustomerDetail::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    private function imageOfType(string $type): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', $type);
    }

    public function idFront(): MorphOne
    {
        return $this->imageOfType('id_front');
    }
    public function idBack(): MorphOne
    {
        return $this->imageOfType('id_back');
    }
    public function licenseFront(): MorphOne
    {
        return $this->imageOfType('license_front');
    }
    public function licenseBack(): MorphOne
    {
        return $this->imageOfType('license_back');
    }
    public function passportImage(): MorphOne
    {
        return $this->imageOfType('passport');
    }

    public function getFullPhoneAttribute(): string
    {
        return trim($this->country_code . $this->phone_number);
    }

    protected static function booted(): void
    {
        // Remove the files from disk when a customer is deleted.
        static::deleting(function (Customer $customer) {
            $customer->images->each->deleteWithFile();
        });
    }
}
