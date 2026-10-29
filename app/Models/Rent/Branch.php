<?php

declare(strict_types=1);

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['name', 'address'];

    public function destinations(): HasMany
    {
        return $this->hasMany(BranchDestination::class, 'branch_id');
    }

    public function destinationForBranches(): HasMany
    {
        return $this->hasMany(BranchDestination::class, 'destination_branch_id');
    }
}
