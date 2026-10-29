<?php

namespace App\Models\Rent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchDestination extends Model
{
    protected $fillable = ['branch_id', 'destination_branch_id', 'price'];
    protected $casts = ['price' => 'decimal:2'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }
}
