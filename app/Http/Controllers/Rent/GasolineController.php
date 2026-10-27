<?php

namespace App\Http\Controllers\Rent;

use App\Models\Rent\{Gasoline, Vehicle, CarReservation, RentalContract};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GasolineController extends FinanceAdjustmentController
{
    protected function model(): string
    {
        return Gasoline::class;
    }
    protected function kind(): string
    {
        return 'gasoline';
    }
    public function vehicles(Request $request)
    {
        $s = trim((string)$request->query('search', ''));
        return response()->json(['data' => Vehicle::query()->whereNotIn('id', fn($q) => $q->select('vehicle_id')->from('sold_cars')->whereNotNull('vehicle_id'))->when($s, fn($q) => $q->where('number', 'like', "%{$s}%"))->orderBy('number')->limit(60)->get(['id', 'number', 'company_id'])]);
    }
    public function resolve(Request $request)
    {
        $d = $request->validate(['car_id' => 'required|integer|exists:vehicles,id', 'date' => 'required|date_format:Y-m-d']);
        $day = $d['date'];
        $res = CarReservation::with('booking')->where('car_id', $d['car_id'])->whereDate('pickup_start_datetime', '<=', $day)->whereDate('dropoff_end_datetime', '>=', $day)->orderByDesc('pickup_start_datetime')->first();
        $contract = $res?->booking?->id ? RentalContract::where('booking_id', $res->booking->id)->first() : null;
        return response()->json(['data' => ['contract_id' => $contract?->id, 'customer_name' => $contract ? $this->context($contract)['customer_name'] : null]]);
    }
}
