<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;

class Gasoline extends Model
{
    protected $table = 'gasoline';
    protected $fillable = ['car_id', 'contract_id', 'transportation_id', 'date', 'amount', 'company_name', 'note'];
    protected $casts = ['amount' => 'decimal:2', 'date' => 'date:Y-m-d'];
    public function contract()
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
    public function car()
    {
        return $this->belongsTo(Vehicle::class, 'car_id');
    }
}
