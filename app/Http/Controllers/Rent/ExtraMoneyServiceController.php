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
        return response()->json(['data' => ExtraMoneyService::whereRaw('LOWER(TRIM(name)) <> ?', ['croka'])->orderBy('name')->get()]);
    }
    private function valid(Request $r): array
    {
        return $r->validate(['name' => 'required|string|max:190', 'price' => 'required|numeric|min:0|max:9999999']);
    }
    public function store(Request $r): JsonResponse
    {
        $data = $this->valid($r);
        if (mb_strtolower(trim($data['name'])) === 'croka') throw ValidationException::withMessages(['name' => ['Croka is a built-in option on the Extra Money form.']]);
        return response()->json(['data' => ExtraMoneyService::create($data)], 201);
    }
    public function update(Request $r, ExtraMoneyService $service): JsonResponse
    {
        $data = $this->valid($r);
        if (mb_strtolower(trim($data['name'])) === 'croka') throw ValidationException::withMessages(['name' => ['Croka is a built-in option on the Extra Money form.']]);
        $service->update($data);
        return response()->json(['data' => $service]);
    }
    public function destroy(ExtraMoneyService $service): JsonResponse
    {
        if ($service->extraMoney()->exists()) throw ValidationException::withMessages(['service' => ['This service is used by Extra Money records and cannot be deleted.']]);
        $service->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
