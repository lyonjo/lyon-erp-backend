<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';
    protected $fillable = ['contract_id', 'booking_id', 'user_id', 'user_name', 'customer_id', 'customer_company_id', 'lawsuit_id', 'sold_car_id', 'bank_id', 'payment_for', 'payment_type', 'is_bank_transferee', 'date', 'payment_value', 'company_value', 'check_bank', 'check_no', 'check_due_date', 'auth_number', 'credit_card_number'];
    protected $casts = ['date' => 'date:Y-m-d', 'check_due_date' => 'date:Y-m-d', 'payment_value' => 'decimal:2', 'company_value' => 'decimal:2', 'is_bank_transferee' => 'boolean'];
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function customerCompany(): BelongsTo
    {
        return $this->belongsTo(CustomerCompany::class);
    }
    public function soldCar(): BelongsTo
    {
        return $this->belongsTo(SoldCar::class);
    }
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
