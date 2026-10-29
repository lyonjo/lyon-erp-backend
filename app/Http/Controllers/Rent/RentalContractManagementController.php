<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{RentalContract, Booking, Vehicle, ExtraItem, Setting, CustomerDetail};
use Carbon\Carbon;
use App\Support\Rent\BillableRentalDays;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalContractManagementController extends Controller
{
    private function relations(): array
    {
        return [
            'booking.customer.details',
            'booking.customerCompany',
            'booking.car.brand',
            'booking.car.model',
            'booking.company',
            'booking.rentalPrice.periods.intervals',
            'booking.extraItems',
            'booking.reservation',
            'additionalDrivers'
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int)$request->integer('per_page', 15)));
        $search = trim((string)$request->query('search', ''));
        $rows = RentalContract::query()->with(['booking.customer', 'booking.customerCompany', 'booking.car.brand', 'booking.car.model'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->query('status')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('booking', function ($b) use ($search) {
                            $b->where('id', 'like', "%{$search}%")
                                ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('customerCompany', fn($c) => $c->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('car', fn($v) => $v->where('number', 'like', "%{$search}%"));
                        });
                });
            })->orderByDesc('id')->paginate($perPage);
        return response()->json($rows);
    }

    private function settings(): array
    {
        $map = Setting::whereIn('key', ['car_rental_km_daily', 'car_rental_price_per_km', 'currency', 'booking_tax_percent', 'additional_hours'])->pluck('value', 'key');
        return [
            'km_daily' => max(0, (float)($map['car_rental_km_daily'] ?? 0)),
            'price_per_km' => max(0, (float)($map['car_rental_price_per_km'] ?? 0)),
            'currency' => strtoupper((string)($map['currency'] ?? 'JOD')),
            'tax_percent' => max(0, (float)($map['booking_tax_percent'] ?? 0)),
            'additional_hours' => BillableRentalDays::allowance(),
        ];
    }

    private function customerDetails($customer): array
    {
        $d = $customer?->details;
        $date = fn($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v ? substr((string)$v, 0, 10) : null);
        return [
            'name' => $customer?->name,
            'email' => $customer?->email,
            'country_code' => $customer?->country_code,
            'phone_number' => $customer?->phone_number,
            'address' => $customer?->address,
            'nationality' => $d?->nationality,
            'place_of_birth' => $d?->place_of_birth,
            'date_of_birth' => $date($d?->date_of_birth),
            'passport_place_of_issue' => $d?->passport_place_of_issue,
            'passport_date_of_issue' => $date($d?->passport_date_of_issue),
            'passport_date_of_expire' => $date($d?->passport_date_of_expire),
            'license_no' => $d?->license_no,
            'license_place_of_issue' => $d?->license_place_of_issue,
            'license_date_of_issue' => $date($d?->license_date_of_issue),
            'license_date_of_expire' => $date($d?->license_date_of_expire),
        ];
    }

    public function show(RentalContract $contract): JsonResponse
    {
        $contract->load($this->relations());
        $booking = $contract->booking;
        abort_unless($booking && $booking->service === 'rent', 404);
        return response()->json(['data' => [
            'contract' => $contract,
            'customer_details' => $this->customerDetails($booking->customer),
            'extra_items' => ExtraItem::orderBy('name')->get(['id', 'name', 'price', 'is_daily']),
            'settings' => $this->settings(),
        ]]);
    }

    // This preview is authoritative for the quoted daily rate, but Save/Close revalidate in a transaction.
    private function pricing(Booking $booking, Carbon $end): array
    {
        $start = Carbon::parse(substr((string)$booking->start_date, 0, 10) . ' ' . substr((string)$booking->start_time, 0, 8));
        if ($end->lessThanOrEqualTo($start)) throw ValidationException::withMessages(['end_date' => ['Drop-off must be later than pickup.']]);
        $days = BillableRentalDays::count($start, $end);
        if ($days > 365) throw ValidationException::withMessages(['end_date' => ['Rental exceeds 365 days.']]);
        $price = $booking->rentalPrice;
        if (!$price) throw ValidationException::withMessages(['end_date' => ['Booking rental pricing record is missing.']]);
        $period = $price->periods()->whereDate('start_date', '<=', $start->toDateString())
            ->whereDate('end_date', '>=', $end->toDateString())->orderByDesc('id')->first();
        if (!$period) throw ValidationException::withMessages(['end_date' => ['Drop-off is outside this booking’s rental pricing period.']]);
        $interval = $period->intervals()->where('start', '<=', $days)->where('end', '>=', $days)->orderByDesc('id')->first();
        if (!$interval) throw ValidationException::withMessages(['end_date' => ['No rental price interval covers the new number of days.']]);
        return [$days, (float)$interval->price, $start];
    }

    private function validation(Request $request): array
    {
        $data = $request->validate([
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'km_in' => ['nullable', 'integer', 'min:0'],
            'extra_item_ids' => ['sometimes', 'array'],
            'extra_item_ids.*' => ['integer', 'distinct', 'exists:extra_items,id'],
            'additional_driver' => ['nullable', 'array'],
            'additional_driver.name' => ['nullable', 'string', 'max:190'],
            'additional_driver.license_number' => ['nullable', 'string', 'max:190'],
            'additional_driver.date_of_issue' => ['nullable', 'date_format:Y-m-d'],
            'additional_driver.expiry_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:additional_driver.date_of_issue'],
            'additional_driver.place_of_issue' => ['nullable', 'string', 'max:190'],
            'customer_details' => ['sometimes', 'array'],
            'customer_details.name' => ['nullable', 'string', 'max:190'],
            'customer_details.email' => ['nullable', 'email', 'max:190'],
            'customer_details.country_code' => ['nullable', 'string', 'max:15'],
            'customer_details.phone_number' => ['nullable', 'string', 'max:50'],
            'customer_details.address' => ['nullable', 'string', 'max:255'],
            'customer_details.nationality' => ['nullable', 'string', 'max:190'],
            'customer_details.date_of_birth' => ['nullable', 'date_format:Y-m-d'],
            'customer_details.place_of_birth' => ['nullable', 'string', 'max:190'],
            'customer_details.passport_place_of_issue' => ['nullable', 'string', 'max:190'],
            'customer_details.passport_date_of_issue' => ['nullable', 'date_format:Y-m-d'],
            'customer_details.passport_date_of_expire' => ['nullable', 'date_format:Y-m-d'],
            'customer_details.license_no' => ['nullable', 'string', 'max:190'],
            'customer_details.license_place_of_issue' => ['nullable', 'string', 'max:190'],
            'customer_details.license_date_of_issue' => ['nullable', 'date_format:Y-m-d'],
            'customer_details.license_date_of_expire' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $driver = $data['additional_driver'] ?? [];
        $keys = ['name', 'license_number', 'date_of_issue', 'expiry_date', 'place_of_issue'];
        $filled = collect($keys)->filter(fn($k) => isset($driver[$k]) && trim((string)$driver[$k]) !== '')->count();
        if ($filled > 0 && $filled !== count($keys)) {
            throw ValidationException::withMessages(['additional_driver' => ['Fill in all additional driver fields or leave them all blank.']]);
        }
        return $data;
    }

    public function update(Request $request, RentalContract $contract): JsonResponse
    {
        return $this->save($request, $contract, false);
    }
    public function close(Request $request, RentalContract $contract): JsonResponse
    {
        return $this->save($request, $contract, true);
    }

    private function save(Request $request, RentalContract $contract, bool $close): JsonResponse
    {
        $data = $this->validation($request);
        $updated = DB::transaction(function () use ($contract, $data, $close) {
            $contract = RentalContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            if ($contract->status !== 'open') throw ValidationException::withMessages(['contract' => ['Closed contracts cannot be modified.']]);
            $booking = Booking::whereKey($contract->booking_id)->lockForUpdate()->firstOrFail();
            if ($booking->service !== 'rent' || !in_array($booking->status, ['relayed', 'realyed'], true) || !$booking->car_id) {
                throw ValidationException::withMessages(['booking' => ['The booking is not an active relayed rental.']]);
            }
            $car = Vehicle::whereKey($booking->car_id)->lockForUpdate()->firstOrFail();
            $reservation = $booking->reservation()->lockForUpdate()->first();
            if (!$reservation) throw ValidationException::withMessages(['booking' => ['The booking has no car reservation.']]);
            $end = Carbon::createFromFormat('!Y-m-d H:i', $data['end_date'] . ' ' . $data['end_time']);
            [$days, $dailyRate, $start] = $this->pricing($booking, $end);
            $collision = DB::table('car_reservations')->join('bookings', 'bookings.id', '=', 'car_reservations.booking_id')
                ->where('car_reservations.car_id', $car->id)->where('car_reservations.id', '!=', $reservation->id)
                ->whereNotIn('bookings.status', ['rejected'])
                ->where('car_reservations.pickup_start_datetime', '<', $end->format('Y-m-d H:i:s'))
                ->where('car_reservations.dropoff_end_datetime', '>', $start->format('Y-m-d H:i:s'))->exists();
            if ($collision) throw ValidationException::withMessages(['end_date' => ['The car is reserved by another booking in this period.']]);
            $kmOut = (int)$contract->km_out;
            $kmIn = isset($data['km_in']) ? (int)$data['km_in'] : null;
            if ($kmIn !== null && $kmIn < $kmOut) throw ValidationException::withMessages(['km_in' => ['KM In cannot be less than KM Out.']]);
            if ($close && $kmIn === null) throw ValidationException::withMessages(['km_in' => ['KM In is required to close the contract.']]);
            $cfg = $this->settings();
            $allowedKm = $cfg['km_daily'] * $days;
            $extraKmCharge = $kmIn === null ? 0.0 : round(max(0, $kmIn - $kmOut - $allowedKm) * $cfg['price_per_km'], 2);
            $ids = $data['extra_item_ids'] ?? [];
            $items = ExtraItem::whereIn('id', $ids)->get();
            if ($items->count() !== count($ids)) throw ValidationException::withMessages(['extra_item_ids' => ['Invalid extra items selected.']]);
            $pivot = [];
            $extraCharge = 0.0;
            foreach ($items as $item) {
                $unit = (float)$item->price;
                $line = round($unit * ($item->is_daily ? $days : 1), 2);
                $pivot[$item->id] = ['unit_price' => $unit, 'is_daily' => (bool)$item->is_daily, 'line_total' => $line];
                $extraCharge += $line;
            }
            $booking->extraItems()->sync($pivot);
            $booking->end_date = $data['end_date'];
            $booking->end_time = $data['end_time'] . ':00';
            $booking->total_days = $days;
            $booking->day_price = $dailyRate;
            $booking->day_company_price = $dailyRate;
            $booking->extra_charge = round($extraCharge, 2);
            $booking->extra_company_charge = round($extraCharge, 2);
            $booking->extra_km_price = $extraKmCharge;
            $booking->total = round($dailyRate * $days + $extraCharge + $extraKmCharge + (float)$booking->branch_fee + (float)$booking->tax, 2);
            $booking->company_total = round($dailyRate * $days + $extraCharge + $extraKmCharge + (float)$booking->branch_fee + (float)$booking->tax, 2);
            $booking->save();
            $reservation->dropoff_end_datetime = $end->format('Y-m-d H:i:s');
            $reservation->save();
            $driver = $data['additional_driver'] ?? [];
            $hasDriver = collect($driver)->contains(fn($v) => $v !== null && trim((string)$v) !== '');
            if ($hasDriver) {
                $existing = $contract->additionalDrivers()->first();
                if ($existing) $existing->update($driver);
                else $contract->additionalDrivers()->create($driver);
            } else {
                $contract->additionalDrivers()->delete();
            }
            if ($booking->customer_id && isset($data['customer_details'])) {
                $customer = $booking->customer()->lockForUpdate()->first();
                if ($customer) {
                    $fields = $data['customer_details'];
                    $customer->fill(collect($fields)->only(['name', 'email', 'country_code', 'phone_number', 'address'])->all());
                    $customer->save();
                    $detailFields = collect($fields)->only([
                        'nationality',
                        'place_of_birth',
                        'date_of_birth',
                        'passport_place_of_issue',
                        'passport_date_of_issue',
                        'passport_date_of_expire',
                        'license_no',
                        'license_place_of_issue',
                        'license_date_of_issue',
                        'license_date_of_expire',
                    ])->all();
                    if ($detailFields) $customer->details()->updateOrCreate(['customer_id' => $customer->id], $detailFields);
                }
            }
            $contract->km_in = $kmIn;
            if ($close) {
                $contract->status = 'closed';
                $booking->status = 'closed';
                $booking->save();
                // The booking's historical reservation is kept for audit purposes.
                $car->current_km = $kmIn;
                $car->save();
            }
            $contract->save();
            return $contract->fresh()->load($this->relations());
        }, 3);
        return response()->json(['message' => $close ? 'Contract closed.' : 'Contract updated.', 'data' => $updated]);
    }
}
