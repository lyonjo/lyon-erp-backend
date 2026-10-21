<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

class Booking extends Model
{
    protected $fillable = ['customer_id', 'customer_company_id', 'company_id', 'car_id', 'service', 'day_price', 'day_company_price', 'total_days', 'extra_charge', 'extra_company_charge', 'extra_km_price', 'tax', 'currency', 'is_relayed', 'relayed_date', 'status', 'ticket_number', 'total', 'branch_fee', 'company_total', 'start_date', 'start_time', 'end_date', 'end_time', 'pick_up_location', 'return_location', 'payment_method', 'is_taxable'];
    protected $casts = ['is_relayed' => 'boolean', 'is_taxable' => 'boolean', 'day_price' => 'decimal:2', 'day_company_price' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'company_total' => 'decimal:2'];
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
    public function car(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'car_id');
    }
    public function extraItems(): BelongsToMany
    {
        return $this->belongsToMany(ExtraItem::class, 'booking_extra_item', 'booking_id', 'extra_item_id')->withPivot(['unit_price', 'is_daily', 'line_total'])->withTimestamps();
    }
}
