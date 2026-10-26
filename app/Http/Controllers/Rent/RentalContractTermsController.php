<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\UpdateRentalContractTermsRequest;
use App\Models\Rent\RentalContractTerms;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RentalContractTermsController extends Controller
{
    public function show(): JsonResponse
    {
        $terms = RentalContractTerms::with('clauses')->find(1);
        return response()->json(['data' => [
            'paragraph_en' => $terms?->paragraph_en ?? '',
            'paragraph_ar' => $terms?->paragraph_ar ?? '',
            'clauses' => $terms?->clauses->map(fn($clause) => [
                'id' => $clause->id,
                'sort_order' => $clause->sort_order,
                'text_en' => $clause->text_en,
                'text_ar' => $clause->text_ar,
            ])->values()->all() ?? [],
        ]]);
    }

    public function update(UpdateRentalContractTermsRequest $request): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $terms = RentalContractTerms::query()->firstOrCreate(['id' => 1], [
                'paragraph_en' => '',
                'paragraph_ar' => '',
            ]);
            // Lock singleton row: concurrent saves cannot interleave clause replacement.
            $terms = RentalContractTerms::query()->lockForUpdate()->findOrFail($terms->id);
            $terms->update([
                'paragraph_en' => ($data['paragraph_en'] ?? ''),
                'paragraph_ar' => ($data['paragraph_ar'] ?? ''),
            ]);
            $terms->clauses()->delete();
            foreach ($data['clauses'] as $index => $clause) {
                $terms->clauses()->create([
                    'sort_order' => $index + 1,
                    'text_en' => $clause['text_en'],
                    'text_ar' => $clause['text_ar'],
                ]);
            }
        });

        return $this->show();
    }
}
