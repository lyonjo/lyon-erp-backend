<?php
namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $table = 'services';
    protected $fillable = ['name'];

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'service_supplier', 'service_id', 'supplier_id');
    }
}
