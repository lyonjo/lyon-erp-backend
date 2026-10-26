<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\ExtraMoneyService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Validation\ValidationException;

class ExtraMoneyServiceController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(['data' => ExtraMoneyService::orderBy('name')->get()]);
    }
    private function valid(Request $r): array
    {
        return $r->validate(['name' => 'required|string|max:190', 'price' => 'required|numeric|min:0|max:9999999', 'has_lawsuit' => 'required|boolean']);
    }
    public function store(Request $r): JsonResponse
    {
        return response()->json(['data' => ExtraMoneyService::create($this->valid($r))], 201);
    }
    public function update(Request $r, ExtraMoneyService $service): JsonResponse
    {
        $service->update($this->valid($r));
        return response()->json(['data' => $service]);
    }
    public function destroy(ExtraMoneyService $service): JsonResponse
    {
        if ($service->extraMoney()->exists()) throw ValidationException::withMessages(['service' => ['This service is used by Extra Money records and cannot be deleted.']]);
        $service->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
