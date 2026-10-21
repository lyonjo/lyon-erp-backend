<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\BookingRentRequest;
use App\Models\Rent\{Booking, CarRentalPrice, Vehicle, Branch, Customer, CustomerCompany, ExtraItem, Setting};
use Carbon\CarbonImmutable;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

class BookingRentController extends Controller
{
    private const BLOCKING = ['pending', 'online_pending', 'approved', 'realyed'];
    private function settings(): array
    {
        $stored = Setting::query()->whereIn('key', ['booking_tax_percent', 'currency'])->pluck('value', 'key');
        return ['tax_percent' => (float)($stored['booking_tax_percent'] ?? 0), 'currency' => strtoupper($stored['currency'] ?? 'JOD')];
    }
    private function duration(array $d): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $d['start_date'] . ' ' . $d['start_time']);
        $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $d['end_date'] . ' ' . $d['end_time']);
        if (!$start || !$end || $end->lessThanOrEqualTo($start)) throw ValidationException::withMessages(['end_date' => ['End date/time must be after start date/time.']]);
        $days = (int)ceil($start->diffInMinutes($end) / 1440);
        if ($days < 1 || $days > 365) throw ValidationException::withMessages(['end_date' => ['Rental must be between 1 and 365 billed days.']]);
        return [$start, $end, $days];
    }
    private function basePrice(Vehicle $car): ?CarRentalPrice
    {
        return CarRentalPrice::query()->where('brand_id', $car->brand_id)->where('model_id', $car->model_id)->where('model_year', $car->model_year)->where('client_type', 'customer')->latest('id')->first();
    }
    private function companyPrice(Vehicle $car, int $companyId): ?CarRentalPrice
    {
        return CarRentalPrice::query()->where('brand_id', $car->brand_id)->where('model_id', $car->model_id)->where('model_year', $car->model_year)->where('client_type', 'company')->where('company_id', $companyId)->latest('id')->first();
    }
    private function rate(?CarRentalPrice $price, CarbonImmutable $start, CarbonImmutable $end, int $days): ?float
    {
        if (!$price) return null;
        // A pricing period must cover the ENTIRE rental, not only the start day.
        $period = $price->periods()->whereDate('start_date', '<=', $start->toDateString())->whereDate('end_date', '>=', $end->toDateString())->orderByDesc('id')->first();
        if (!$period) return null;
        $interval = $period->intervals()->where('start', '<=', $days)->where('end', '>=', $days)->orderByDesc('id')->first();
        return $interval ? (float)$interval->price : null;
    }
    private function overlaps(int $carId, CarbonImmutable $start, CarbonImmutable $end, ?int $except = null): bool
    {
        $q = Booking::query()->where('car_id', $carId)->whereIn('status', self::BLOCKING)
            ->whereRaw('TIMESTAMP(start_date, start_time) < ?', [$end->format('Y-m-d H:i:s')])
            ->whereRaw('TIMESTAMP(end_date, end_time) > ?', [$start->format('Y-m-d H:i:s')]);
        if ($except) $q->where('id', '!=', $except);
        return $q->exists();
    }
    private function candidate(Vehicle $car, array $data, CarbonImmutable $start, CarbonImmutable $end, int $days): ?array
    {
        $base = $this->basePrice($car);
        $baseRate = $this->rate($base, $start, $end, $days);
        if ($baseRate === null || $this->overlaps($car->id, $start, $end)) return null;
        $companyRate = null;
        if ($data['client_type'] === 'company') {
            $override = $this->companyPrice($car, (int)$data['customer_company_id']);
            $companyRate = $this->rate($override, $start, $end, $days);
            if ($companyRate === null) return null;
        }
        return ['car_id' => $car->id, 'number' => $car->number, 'brand' => $car->brand?->name, 'model' => $car->model?->name, 'model_year' => $car->model_year, 'company_id' => $car->company_id, 'company_name' => $car->company?->name, 'current_location' => $car->current_location, 'branch_id' => $car->branch_id, 'day_price' => $data['client_type'] === 'company' ? $companyRate : $baseRate, 'day_company_price' => $data['client_type'] === 'company' ? $companyRate : $baseRate, 'total_days' => $days];
    }
    public function options(Request $request): JsonResponse
    {
        $companiesName = Schema::hasColumn('customer_companies', 'name') ? 'name' : 'company_name';
        $customersName = Schema::hasColumn('customers', 'name') ? 'name' : (Schema::hasColumn('customers', 'customer_name') ? 'customer_name' : 'id');
        return response()->json(['data' => [
            'customers' => Customer::query()->select('id')->selectRaw("`{$customersName}` as name")->orderBy($customersName)->limit(1000)->get(),
            'customer_companies' => CustomerCompany::query()->select('id')->selectRaw("`{$companiesName}` as name")->orderBy($companiesName)->limit(1000)->get(),
            'branches' => Branch::query()->select('id', 'name')->orderBy('name')->get(),
            'extra_items' => ExtraItem::query()->select('id', 'name', 'price', 'is_daily')->orderBy('name')->get(),
            'settings' => $this->settings(),
        ]]);
    }
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_type' => ['required', 'in:customer,company'],
            'customer_id' => ['required_if:client_type,customer', 'nullable', 'exists:customers,id'],
            'customer_company_id' => ['required_if:client_type,company', 'nullable', 'exists:customer_companies,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'pick_up_branch_id' => ['required', 'exists:branches,id'],
            'return_branch_id' => ['required', 'exists:branches,id'],
        ]);
        [$start, $end, $days] = $this->duration($data);
        $rows = [];
        Vehicle::query()->with(['brand:id,name', 'model:id,name', 'company:id,name'])->where('status', 1)->orderBy('id')->chunkById(150, function ($cars) use (&$rows, $data, $start, $end, $days) {
            foreach ($cars as $car) {
                $row = $this->candidate($car, $data, $start, $end, $days);
                if ($row) $rows[] = $row;
            }
        });
        return response()->json(['data' => $rows, 'meta' => ['total_days' => $days, 'count' => count($rows)]]);
    }
    public function store(BookingRentRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$start, $end, $days] = $this->duration($data);
        $booking = DB::transaction(function () use ($data, $start, $end, $days) {
            // A vehicle row lock serializes concurrent booking creations for the same vehicle.
            $car = Vehicle::query()->with(['brand:id,name', 'model:id,name', 'company:id,name'])->whereKey($data['car_id'])->lockForUpdate()->firstOrFail();
            if (!(int)$car->status) throw ValidationException::withMessages(['car_id' => ['Vehicle is inactive.']]);
            $row = $this->candidate($car, $data, $start, $end, $days);
            if (!$row) throw ValidationException::withMessages(['car_id' => ['Vehicle unavailable or no applicable pricing for the entire rental period and duration.']]);
            $pickup = Branch::query()->findOrFail($data['pick_up_branch_id']);
            $return = Branch::query()->findOrFail($data['return_branch_id']);
            // Compare branch identity through vehicle.branch_id; free text is not reliable.
            $branchFee = (int)$car->branch_id === (int)$pickup->id ? 0 : 5;
            $items = ExtraItem::query()->whereIn('id', $data['extra_item_ids'] ?? [])->get();
            $extra = 0;
            $sync = [];
            foreach ($items as $item) {
                $line = round((float)$item->price * ($item->is_daily ? $days : 1), 2);
                $extra += $line;
                $sync[$item->id] = ['unit_price' => $item->price, 'is_daily' => (bool)$item->is_daily, 'line_total' => $line];
            }
            $extra = round($extra, 2);
            $daily = (float)$row['day_price'];
            $companyDaily = (float)$row['day_company_price'];
            $settings = $this->settings();
            $taxable = (bool)$data['is_taxable'];
            $tax = $taxable ? round(($daily * $days + $extra) * $settings['tax_percent'] / 100, 2) : 0;
            $booking = Booking::create([
                'customer_id' => $data['client_type'] === 'customer' ? $data['customer_id'] : null,
                'customer_company_id' => $data['client_type'] === 'company' ? $data['customer_company_id'] : null,
                'company_id' => $car->company_id,
                'car_id' => $car->id,
                'service' => 'rent',
                'day_price' => $daily,
                'day_company_price' => $companyDaily,
                'total_days' => $days,
                'extra_charge' => $extra,
                'extra_company_charge' => $extra,
                'extra_km_price' => 0,
                'tax' => $tax,
                'currency' => $settings['currency'],
                'is_relayed' => false,
                'relayed_date' => null,
                'status' => 'pending',
                'ticket_number' => null,
                'total' => round($daily * $days + $extra + $tax + $branchFee, 2),
                'branch_fee' => $branchFee,
                'company_total' => round($companyDaily * $days + $extra + $tax + $branchFee, 2),
                'start_date' => $data['start_date'],
                'start_time' => $data['start_time'],
                'end_date' => $data['end_date'],
                'end_time' => $data['end_time'],
                'pick_up_location' => $pickup->name,
                'return_location' => $return->name,
                'payment_method' => $data['payment_method'],
                'is_taxable' => $taxable,
            ]);
            $booking->extraItems()->sync($sync);
            return $booking;
        }, 3);
        return response()->json(['data' => $booking->load(['car.brand', 'car.model', 'extraItems'])], 201);
    }
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['tab' => ['nullable', 'in:pending,reservations'], 'search' => ['nullable', 'string', 'max:120'], 'per_page' => ['nullable', 'integer', 'between:1,100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $q = Booking::query()->with(['customer', 'customerCompany', 'car.brand', 'car.model'])->where('service', 'rent');
        $q->whereIn('status', ($data['tab'] ?? 'pending') === 'pending' ? ['pending', 'online_pending'] : ['approved']);
        if (!empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $q->where(fn($x) => $x->where('id', 'like', $term)->orWhereHas('car', fn($v) => $v->where('number', 'like', $term)));
        }
        return response()->json($q->orderByDesc('id')->paginate($data['per_page'] ?? 15));
    }
    public function show(Booking $booking): JsonResponse
    {
        abort_unless($booking->service === 'rent', 404);
        return response()->json(['data' => $booking->load(['customer', 'customerCompany', 'car.brand', 'car.model', 'extraItems'])]);
    }
}
