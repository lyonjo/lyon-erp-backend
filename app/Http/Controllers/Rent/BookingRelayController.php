<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Booking, Vehicle, ExtraItem, Setting};
use App\Models\Rent\RentalContract;
use App\Models\Rent\ContractAdditionalDriver;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class BookingRelayController extends Controller
{
    public function show(Booking $booking): JsonResponse
    {
        $booking->load(['customer.details', 'customerCompany', 'car.brand', 'car.model', 'company', 'rentalPrice.brand', 'rentalPrice.model', 'rentalPrice.periods.intervals', 'extraItems', 'reservation']);
        if ($booking->service !== 'rent') abort(422, 'Only rental bookings can be relayed.');
        // Normalise the customer profile once on the server. The customer profile
        // API and the relay UI historically used different property names.
        $profile = $booking->customer ?: $booking->customerCompany;
        $details = $this->relayCustomerDetails($profile);

        $currency = strtoupper((string) (Setting::where('key', 'currency')->value('value') ?: 'JOD'));
        return response()->json(['data' => [
            'booking' => $booking,
            'customer' => $profile,
            'customer_details' => $details,
            'customer_license' => null,
            'extra_items' => ExtraItem::orderBy('name')->get(['id', 'name', 'price', 'is_daily']),
            'currency' => $currency,
            'contract_exists' => RentalContract::where('booking_id', $booking->id)->exists(),
        ]]);
    }


    /**
     * The identity and license columns belong to customer_details, not customers.
     * Read them through Customer::details() to match the existing customer profile.
     */
    private function relayCustomerDetails($profile): array
    {
        if (!$profile) return [];

        $details = $profile instanceof \App\Models\Rent\Customer
            ? $profile->details
            : null;

        $formatDate = static function ($value): ?string {
            if ($value === null || $value === '') return null;
            if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
            return substr((string) $value, 0, 10);
        };

        return [
            'name' => $profile->name ?? $profile->supplier_name ?? $profile->company_name ?? null,
            'email' => $profile->email ?? null,
            'phone' => $profile->phone_number ?? $profile->phone ?? null,
            'nationality' => $details?->nationality,
            'address' => $profile->address ?? null,
            // Neither supplied model defines an ID/passport number column.
            'id_number' => null,
            'id_issue_date' => $formatDate($details?->passport_date_of_issue),
            'id_expiry_date' => $formatDate($details?->passport_date_of_expire),
            'id_issue_place' => $details?->passport_place_of_issue,
            'date_of_birth' => $formatDate($details?->date_of_birth),
            'place_of_birth' => $details?->place_of_birth,
            'license_number' => $details?->license_no,
            'license_issue_date' => $formatDate($details?->license_date_of_issue),
            'license_expiry_date' => $formatDate($details?->license_date_of_expire),
            'license_issue_place' => $details?->license_place_of_issue,
        ];
    }

    public function store(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'extra_item_ids' => ['required', 'array'],
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
            $start = Carbon::parse($locked->start_date . ' ' . $locked->start_time);
            $end = Carbon::createFromFormat('!Y-m-d H:i', $data['end_date'] . ' ' . $data['end_time']);
            if (!$end || $end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages(['end_date' => ['Drop-off must be later than pickup.']]);
            }
            $days = max(1, (int) ceil($start->diffInSeconds($end) / 86400));
            if ($days > 365) {
                throw ValidationException::withMessages(['end_date' => ['Rental must be 1–365 billed days.']]);
            }
            // Use the same pricing-period/day-interval rules as BookingRentController.
            // Never allow extending a reservation into dates not covered by this booking's own price record.
            $pricing = $locked->rentalPrice()->first();
            if (!$pricing) {
                throw ValidationException::withMessages(['end_date' => ['This booking has no rental pricing record.']]);
            }
            $period = $pricing->periods()
                ->whereDate('start_date', '<=', $start->toDateString())
                ->whereDate('end_date', '>=', $end->toDateString())
                ->orderByDesc('id')->first();
            if (!$period) {
                throw ValidationException::withMessages(['end_date' => ['The selected drop-off is outside the rental pricing period.']]);
            }
            $interval = $period->intervals()
                ->where('start', '<=', $days)
                ->where('end', '>=', $days)
                ->orderByDesc('id')->first();
            if (!$interval) {
                throw ValidationException::withMessages(['end_date' => ['The rental pricing record has no rate for this number of days.']]);
            }
            $dailyRate = (float) $interval->price;
            // A company-price booking uses that exact company-specific pricing record.
            // The current schema has no second independent fleet-company cost rate.
            $companyDailyRate = $dailyRate;
            $reservation = $locked->reservation()->lockForUpdate()->firstOrFail();
            $overlap = DB::table('car_reservations')
                ->join('bookings', 'bookings.id', '=', 'car_reservations.booking_id')
                ->where('car_reservations.car_id', $locked->car_id)
                ->where('car_reservations.id', '!=', $reservation->id)
                ->where('bookings.status', '!=', 'rejected')
                ->where('car_reservations.pickup_start_datetime', '<', $end->format('Y-m-d H:i:s'))
                ->where('car_reservations.dropoff_end_datetime', '>', $start->format('Y-m-d H:i:s'))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['end_date' => ['This vehicle has another reservation in the selected period.']]);
            }
            $car = Vehicle::whereKey($locked->car_id)->lockForUpdate()->firstOrFail();
            $items = ExtraItem::whereIn('id', $data['extra_item_ids'])->get();
            if ($items->count() !== count($data['extra_item_ids'])) {
                throw ValidationException::withMessages(['extra_item_ids' => ['Invalid extra items.']]);
            }
            $pivot = [];
            $extraCharge = 0.0;
            foreach ($items as $item) {
                $unit = (float)$item->price;
                $line = round($unit * ($item->is_daily ? $days : 1), 2);
                $pivot[$item->id] = ['unit_price' => $unit, 'is_daily' => (bool)$item->is_daily, 'line_total' => $line];
                $extraCharge += $line;
            }
            $locked->end_date = $data['end_date'];
            $locked->end_time = $data['end_time'] . ':00';
            $locked->total_days = $days;
            $locked->day_price = $dailyRate;
            $locked->day_company_price = $companyDailyRate;
            $locked->extraItems()->sync($pivot);
            $locked->extra_charge = round($extraCharge, 2);
            $locked->extra_company_charge = round($extraCharge, 2);
            $locked->total = round(((float)$locked->day_price * $days) + $extraCharge + (float)$locked->tax + (float)$locked->branch_fee + (float)$locked->extra_km_price, 2);
            $locked->company_total = round(((float)$locked->day_company_price * $days) + $extraCharge + (float)$locked->tax + (float)$locked->branch_fee + (float)$locked->extra_km_price, 2);
            $locked->status = 'relayed';
            $locked->is_relayed = true;
            $locked->relayed_date = now();
            $locked->save();
            $reservation->dropoff_end_datetime = $end->format('Y-m-d H:i:s');
            $reservation->save();
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
