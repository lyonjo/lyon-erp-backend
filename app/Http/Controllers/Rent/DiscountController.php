<?php

namespace App\Http\Controllers\Rent;

use App\Models\Rent\Discount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscountController extends FinanceAdjustmentController
{
    protected function model(): string
    {
        return Discount::class;
    }
    protected function kind(): string
    {
        return 'discount';
    }
    protected function data(\Illuminate\Http\Request $r): array
    {
        return $r->validate(['contract_id' => 'required|integer|exists:contracts,id', 'transportation_id' => 'nullable|integer', 'amount' => 'required|numeric|min:0', 'note' => 'nullable|string|max:4000']);
    }
    public function approve(int $id)
    {
        return DB::transaction(function () use ($id) {
            $r = Discount::lockForUpdate()->findOrFail($id);
            if (!$r->is_approved) {
                $r->is_approved = true;
                $r->approved_by = array_merge($r->approved_by ?? [], [['id' => auth()->id(), 'name' => auth()->user()?->name]]);
                $r->save();
            }
            return response()->json(['data' => $r]);
        });
    }
}
