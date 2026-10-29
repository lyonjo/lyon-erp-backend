<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Booking, Vehicle, ExtraItem, Setting};
use App\Models\Rent\RentalContract;
use App\Models\Rent\ContractAdditionalDriver;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;
use App\Support\Rent\BillableRentalDays;

class BookingRelayController extends Controller
{
    public function show(Booking $booking): JsonResponse
    {
        $booking->load(['customer', 'customerCompany', 'car.brand', 'car.model', 'company', 'rentalPrice.brand', 'rentalPrice.model', 'extraItems', 'reservation']);
        if ($booking->service !== 'rent') abort(422, 'Only rental bookings can be relayed.');
        $currency = strtoupper((string) (Setting::where('key', 'currency')->value('value') ?: 'JOD'));
        return response()->json(['data' => [
            'booking' => $booking,
            'customer' => $booking->customer ?: $booking->customerCompany,
            'customer_license' => $booking->customer?->license ?? $booking->customer?->driverLicense ?? null,
            'extra_items' => ExtraItem::orderBy('name')->get(['id', 'name', 'price', 'is_daily']),
            'currency' => $currency,
            'additional_hours' => BillableRentalDays::allowance(),
            'contract_exists' => RentalContract::where('booking_id', $booking->id)->exists(),
        ]]);
    }

    public function store(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'extra_item_ids' => ['required', 'array'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'extra_item_ids.*' => ['integer', 'distinct', 'exists:extra_items,id'],
            'additional_driver' => ['nullable', 'array'],
            'additional_driver.name' => ['nullable', 'string', 'max:190'],
            'additional_driver.license_number' => ['nullable', 'string', 'max:190'],
            'additional_driver.date_of_issue' => ['nullable', 'date'],
            'additional_driver.expiry_date' => ['nullable', 'date', 'after:additional_driver.date_of_issue'],
            'additional_driver.place_of_issue' => ['nullable', 'string', 'max:190'],
        ]);
        $driver = $data['additional_driver'] ?? [];
        $driverFields = ['name', 'license_number', 'date_of_issue', 'expiry_date', 'place_of_issue'];
        $filled = collect($driverFields)->filter(fn($key) => isset($driver[$key]) && trim((string)$driver[$key]) !== '');
        if ($filled->count() !== 0 && $filled->count() !== count($driverFields)) {
            throw ValidationException::withMessages(['additional_driver' => ['Complete all additional driver fields or leave all blank.']]);
        }
        $contract = DB::transaction(function () use ($booking, $data, $filled, $driver) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->service !== 'rent' || $locked->status !== 'approved' || !$locked->car_id || !$locked->reservation()->exists()) {
                throw ValidationException::withMessages(['booking' => ['Only approved, car-assigned rental bookings can be relayed.']]);
            }
            if (RentalContract::where('booking_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['booking' => ['This booking already has a contract.']]);
            }
            $car = Vehicle::whereKey($locked->car_id)->lockForUpdate()->firstOrFail();
            $items = ExtraItem::whereIn('id', $data['extra_item_ids'])->get();
            if ($items->count() !== count($data['extra_item_ids'])) {
                throw ValidationException::withMessages(['extra_item_ids' => ['Invalid extra items.']]);
            }
            $start = CarbonImmutable::parse(substr((string)$locked->start_date, 0, 10) . ' ' . substr((string)$locked->start_time, 0, 8));
            $end = CarbonImmutable::parse($data['end_date'] . ' ' . $data['end_time']);
            $days = BillableRentalDays::count($start, $end);
            if ($days < 1 || $days > 365) {
                throw ValidationException::withMessages(['end_date' => ['Drop-off must follow pickup and rental must not exceed 365 billed days.']]);
            }
            $price = $locked->rentalPrice;
            if (!$price) throw ValidationException::withMessages(['end_date' => ['Rental pricing not found.']]);
            $period = $price->periods()->whereDate('start_date', '<=', $start->toDateString())
                ->whereDate('end_date', '>=', $end->toDateString())->orderByDesc('id')->first();
            $interval = $period?->intervals()->where('start', '<=', $days)->where('end', '>=', $days)->orderByDesc('id')->first();
            if (!$interval) throw ValidationException::withMessages(['end_date' => ['No rental rate covers the new duration.']]);
            $daily = (float)$interval->price;
            $reservation = $locked->reservation()->lockForUpdate()->first();
            if (!$reservation) throw ValidationException::withMessages(['booking' => ['Reservation not found.']]);
            $collision = DB::table('car_reservations')->where('car_id', $locked->car_id)
                ->where('id', '!=', $reservation->id)
                ->where('pickup_start_datetime', '<', $end->format('Y-m-d H:i:s'))
                ->where('dropoff_end_datetime', '>', $start->format('Y-m-d H:i:s'))->exists();
            if ($collision) throw ValidationException::withMessages(['end_date' => ['Car is reserved during the new rental period.']]);
            $locked->end_date = $data['end_date'];
            $locked->end_time = $data['end_time'] . ':00';
            $locked->total_days = $days;
            $locked->day_price = $daily;
            $locked->day_company_price = $daily;
            $reservation->dropoff_end_datetime = $end->format('Y-m-d H:i:s');
            $reservation->save();
            $pivot = [];
            $extraCharge = 0.0;
            foreach ($items as $item) {
                $unit = (float)$item->price;
                $line = round($unit * ($item->is_daily ? $days : 1), 2);
                $pivot[$item->id] = ['unit_price' => $unit, 'is_daily' => (bool)$item->is_daily, 'line_total' => $line];
                $extraCharge += $line;
            }
            $locked->extraItems()->sync($pivot);
            $locked->extra_charge = round($extraCharge, 2);
            $locked->extra_company_charge = round($extraCharge, 2);
            $locked->total = round(((float)$locked->day_price * $days) + $extraCharge + (float)$locked->tax + (float)$locked->branch_fee + (float)$locked->extra_km_price, 2);
            $locked->company_total = round(((float)$locked->day_company_price * $days) + $extraCharge + (float)$locked->tax + (float)$locked->branch_fee + (float)$locked->extra_km_price, 2);
            $locked->status = 'relayed';
            $locked->is_relayed = true;
            $locked->relayed_date = now();
            $locked->save();
            $contract = RentalContract::create([
                'booking_id' => $locked->id,
                'km_out' => $car->current_km ?? 0,
                'km_in' => null,
                'status' => 'open',
            ]);
            if ($filled->isNotEmpty()) {
                $contract->additionalDrivers()->create(collect($driver)->only(['name', 'license_number', 'date_of_issue', 'expiry_date', 'place_of_issue'])->all());
            }
            return $contract;
        }, 3);
        return response()->json(['message' => 'Booking relayed and contract created.', 'data' => $contract->load('additionalDrivers')], 201);
    }
}
