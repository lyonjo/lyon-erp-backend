<?php
namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    protected $table = 'suppliers';
    protected $fillable = ['supplier_name','country_code','phone_number','representative_name','representative_country_code','representative_phone','note'];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_supplier', 'supplier_id', 'service_id');
    }
}
