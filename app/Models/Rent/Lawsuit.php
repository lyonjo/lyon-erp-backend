<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Lawsuit extends Model
{
    protected $fillable = ['customer_id', 'customer_company_id', 'suit_number', 'party', 'type', 'company_id', 'extra_money_id', 'vehicle_number', 'croka_number', 'lawyer_id', 'driver_id', 'contract_id', 'amount', 'fees', 'court', 'date', 'note', 'status', 'customer_name'];
    protected $casts = ['date' => 'date:Y-m-d', 'amount' => 'decimal:2', 'fees' => 'decimal:2'];
    public function sessions(): HasMany
    {
        return $this->hasMany(LawsuitSession::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function customerCompany(): BelongsTo
    {
        return $this->belongsTo(CustomerCompany::class);
    }
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'lawyer_id');
    }
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
}
