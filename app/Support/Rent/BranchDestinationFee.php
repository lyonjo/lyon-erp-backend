<?php

namespace App\Support\Rent;

use App\Models\Rent\BranchDestination;
use Illuminate\Validation\ValidationException;

class BranchDestinationFee
{
    /** Returns 0 for a same-branch return; rejects routes not configured for the pickup branch. */
    public static function forRoute(int $pickupId, int $returnId): float
    {
        if ($pickupId === $returnId) return 0.0;

        $record = BranchDestination::query()
            ->where('branch_id', $pickupId)
            ->where('destination_branch_id', $returnId)
            ->first();
        if (!$record) {
            throw ValidationException::withMessages([
                'return_branch_id' => ['The selected return branch is not an allowed destination for this pickup branch.'],
            ]);
        }
        return (float) $record->price;
    }
}
