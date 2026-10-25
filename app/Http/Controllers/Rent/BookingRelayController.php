<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Booking, Vehicle, ExtraItem, Setting};
use App\Models\Rent\RentalContract;
use App\Models\Rent\ContractAdditionalDriver;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingRelayController extends Controller
{
    public function show(Booking $booking): JsonResponse
    {
        $booking->load(['customer', 'customerCompany', 'car.brand', 'car.model', 'company', 'rentalPrice.brand', 'rentalPrice.model', 'extraItems', 'reservation']);
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
            'customer_license' => $booking->customer?->license ?? $booking->customer?->driverLicense ?? null,
            'extra_items' => ExtraItem::orderBy('name')->get(['id', 'name', 'price', 'is_daily']),
            'currency' => $currency,
            'contract_exists' => RentalContract::where('booking_id', $booking->id)->exists(),
        ]]);
    }


    /**
     * Expose the identity/passport and driving-licence information under stable
     * names. These attributes belong to the Customer record, not Booking.
     * Variants here cover both older tenant schemas and newer Customer forms.
     */
    private function relayCustomerDetails($profile): array
    {
        if (!$profile) return [];

        $record = $profile->toArray();
        $raw = $profile->getAttributes();
        $sources = array_merge($record, $raw);
        foreach (['passport', 'identity', 'national_id', 'id_details', 'driver_license', 'license', 'driving_license'] as $relation) {
            if (isset($record[$relation]) && is_array($record[$relation])) {
                foreach ($record[$relation] as $key => $value) {
                    if (!is_array($value)) $sources[$relation . '_' . $key] = $value;
                }
            }
        }
        // Case and underscore-insensitive match; do not confuse ID/passport
        // fields with unrelated database primary keys.
        $normalized = [];
        foreach ($sources as $key => $value) {
            if (is_scalar($value) && $value !== '' && $value !== null) {
                $normalized[strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$key))] = $value;
            }
        }
        $get = static function (array $names) use ($normalized) {
            foreach ($names as $name) {
                $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
                if (isset($normalized[$key])) return $normalized[$key];
            }
            return null;
        };
        return [
            'name' => $get(['name', 'customer_name', 'full_name', 'company_name']),
            'email' => $get(['email', 'email_address']),
            'phone' => $get(['phone', 'phone_number', 'mobile', 'mobile_number', 'telephone']),
            'nationality' => $get(['nationality', 'nationality_name', 'country', 'country_name', 'passport_nationality']),
            'address' => $get(['address', 'customer_address', 'location']),
            'id_number' => $get(['passport_number', 'passport_no', 'id_number', 'identity_number', 'national_id', 'id_no', 'passport_id_number', 'personal_id_number']),
            'id_issue_date' => $get(['passport_issue_date', 'passport_date_of_issue', 'passport_issued_at', 'passport_issued_date', 'id_issue_date', 'id_date_of_issue', 'identity_issue_date', 'date_of_issue', 'passport_date_issue']),
            'id_expiry_date' => $get(['passport_expiry_date', 'passport_expiration_date', 'passport_date_of_expiry', 'passport_expire_date', 'passport_expiry', 'id_expiry_date', 'id_expiration_date', 'identity_expiry_date', 'expiry_date', 'passport_end_date']),
            'id_issue_place' => $get(['passport_issue_place', 'passport_place_of_issue', 'passport_issued_place', 'id_place_of_issue', 'id_issue_place', 'identity_issue_place', 'place_of_issue']),
            'date_of_birth' => $get(['date_of_birth', 'birth_date', 'dob', 'birthday', 'birthdate']),
            'place_of_birth' => $get(['place_of_birth', 'birth_place', 'birthplace']),
            'license_number' => $get(['license_number', 'license_no', 'driver_license_number', 'driving_license_number', 'driving_license_no', 'driver_license_no']),
            'license_issue_date' => $get(['license_issue_date', 'license_date_of_issue', 'driving_license_issue_date', 'driver_license_issue_date', 'driving_license_date_of_issue', 'license_issued_at']),
            'license_expiry_date' => $get(['license_expiry_date', 'license_expiration_date', 'license_date_of_expiry', 'driving_license_expiry_date', 'driver_license_expiry_date', 'driving_license_expiration_date']),
            'license_issue_place' => $get(['license_issue_place', 'license_place_of_issue', 'driving_license_issue_place', 'driver_license_place_of_issue', 'license_issued_place']),
        ];
    }

    public function store(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
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
            $car = Vehicle::whereKey($locked->car_id)->lockForUpdate()->firstOrFail();
            $items = ExtraItem::whereIn('id', $data['extra_item_ids'])->get();
            if ($items->count() !== count($data['extra_item_ids'])) {
                throw ValidationException::withMessages(['extra_item_ids' => ['Invalid extra items.']]);
            }
            $days = max(1, (int)$locked->total_days);
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
