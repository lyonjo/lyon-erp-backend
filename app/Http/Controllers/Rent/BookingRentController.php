<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\BookingRentRequest;
use App\Models\Rent\{Booking, CarRentalPrice, CarReservation, Vehicle, Branch, Customer, CustomerCompany, ExtraItem, Setting};
use Carbon\CarbonImmutable;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

class BookingRentController extends Controller
{
  private const BLOCKING = ['pending', 'online_pending', 'approved', 'realyed'];
  private function settings(): array
  {
    return ['currency' => strtoupper((string)(Setting::where('key', 'currency')->value('value') ?: 'JOD'))];
  }
  private function duration(array $d): array
  {
    $start = CarbonImmutable::parse($d['start_date'] . ' ' . $d['start_time']);
    $end = CarbonImmutable::parse($d['end_date'] . ' ' . $d['end_time']);
    if ($end->lessThanOrEqualTo($start)) throw ValidationException::withMessages(['end_date' => ['End time must be after start time.']]);
    $days = (int)ceil($start->diffInMinutes($end) / 1440);
    if ($days < 1 || $days > 365) throw ValidationException::withMessages(['end_date' => ['Rental must be 1–365 billed days.']]);
    return [$start, $end, $days];
  }
  private function rate(CarRentalPrice $price, CarbonImmutable $start, CarbonImmutable $end, int $days): ?float
  {
    $period = $price->periods()->whereDate('start_date', '<=', $start->toDateString())
      ->whereDate('end_date', '>=', $end->toDateString())->orderByDesc('id')->first();
    if (!$period) return null;
    $interval = $period->intervals()->where('start', '<=', $days)->where('end', '>=', $days)->orderByDesc('id')->first();
    return $interval ? (float)$interval->price : null;
  }
  private function availableVehicles(CarRentalPrice $price, CarbonImmutable $start, CarbonImmutable $end, bool $lock = false): \Illuminate\Support\Collection
  {
    $cars = Vehicle::query()->where('brand_id', $price->brand_id)->where('model_id', $price->model_id)
      ->where('model_year', $price->model_year)->where('status', 1)
      ->whereNotIn('id', DB::table('sold_cars')->select('vehicle_id'))
      ->whereNotIn('id', DB::table('car_reservations as cr')->join('bookings as b', 'b.id', '=', 'cr.booking_id')
        ->select('cr.car_id')->whereIn('b.status', self::BLOCKING)
        ->where('cr.pickup_start_datetime', '<', $end->format('Y-m-d H:i:s'))
        ->where('cr.dropoff_end_datetime', '>', $start->format('Y-m-d H:i:s')))->orderBy('id');
    if ($lock) $cars->lockForUpdate();
    return $cars->get();
  }
  private function matchingPrices(array $data): \Illuminate\Database\Eloquent\Builder
  {
    $q = CarRentalPrice::query()->with(['brand:id,name', 'model:id,name', 'images']);
    if ($data['client_type'] === 'company') return $q->where('client_type', 'company')->where('company_id', $data['customer_company_id']);
    return $q->where('client_type', 'customer')->whereNull('company_id');
  }
  private function validateSearch(Request $r): array
  {
    return $r->validate([
      'client_type' => ['required', 'in:customer,company'],
      'customer_id' => ['required_if:client_type,customer', 'nullable', 'exists:customers,id'],
      'customer_company_id' => ['required_if:client_type,company', 'nullable', 'exists:customer_companies,id'],
      'start_date' => ['required', 'date_format:Y-m-d'],
      'start_time' => ['required', 'date_format:H:i'],
      'end_date' => ['required', 'date_format:Y-m-d'],
      'end_time' => ['required', 'date_format:H:i'],
      'pick_up_branch_id' => ['required', 'exists:branches,id'],
      'return_branch_id' => ['required', 'exists:branches,id']
    ]);
  }

  /** The existing customer and customer-company screens use is_blacklist. */
  private function eligibleClients(\Illuminate\Database\Eloquent\Builder $query, string $table): \Illuminate\Database\Eloquent\Builder
  {
    if (!Schema::hasColumn($table, 'is_blacklist')) throw new \RuntimeException("Missing {$table}.is_blacklist column: cannot safely filter blacklisted clients.");
    return $query->where(function ($q) {
      $q->where('is_blacklist', 0)->orWhereNull('is_blacklist');
    });
  }
  private function ensureEligibleClient(array $data): void
  {
    $company = $data['client_type'] === 'company';
    $table = $company ? 'customer_companies' : 'customers';
    $id = $company ? $data['customer_company_id'] : $data['customer_id'];
    $model = $company ? CustomerCompany::query() : Customer::query();
    if (!$this->eligibleClients($model, $table)->whereKey($id)->exists())
      throw ValidationException::withMessages([$company ? 'customer_company_id' : 'customer_id' => ['The selected client is blacklisted or unavailable.']]);
  }
  public function options(Request $r): JsonResponse
  {
    $companyName = Schema::hasColumn('customer_companies', 'name') ? 'name' : 'company_name';
    $customerName = Schema::hasColumn('customers', 'name') ? 'name' : (Schema::hasColumn('customers', 'customer_name') ? 'customer_name' : 'id');
    return response()->json(['data' => [
      'customers' => $this->eligibleClients(Customer::query(), 'customers')->select('id')->selectRaw("`{$customerName}` as name")->orderBy($customerName)->limit(1000)->get(),
      'customer_companies' => $this->eligibleClients(CustomerCompany::query(), 'customer_companies')->select('id')->selectRaw("`{$companyName}` as name")->orderBy($companyName)->limit(1000)->get(),
      'branches' => Branch::select('id', 'name')->orderBy('name')->get(),
      'extra_items' => ExtraItem::select('id', 'name', 'price', 'is_daily')->orderBy('name')->get(),
      'settings' => $this->settings()
    ]]);
  }
  public function search(Request $r): JsonResponse
  {
    $d = $this->validateSearch($r);
    $this->ensureEligibleClient($d);
    [$start, $end, $days] = $this->duration($d);
    $rows = [];
    // Each pricing ID is a different contractual rate; distinct physical cars are only counted for availability.
    foreach ($this->matchingPrices($d)->orderByDesc('id')->get() as $price) {
      $daily = $this->rate($price, $start, $end, $days);
      if ($daily === null) continue;
      $cars = $this->availableVehicles($price, $start, $end);
      if ($cars->isEmpty()) continue;
      $car = $cars->first();
      $imagePrice = $price;
      // Company pricing has no editable images. Use the matching customer/base images.
      if ($price->client_type === 'company') {
        $base = CarRentalPrice::query()->with('images')->where('client_type', 'customer')->whereNull('company_id')
          ->where('brand_id', $price->brand_id)->where('model_id', $price->model_id)->where('model_year', $price->model_year)
          ->orderByDesc('id')->first();
        if ($base) $imagePrice = $base;
      }
      $main = $imagePrice->images->firstWhere('type', 'main');
      $rows[] = [
        'car_rental_price_id' => $price->id,
        'brand' => $price->brand?->name,
        'model' => $price->model?->name,
        'model_year' => $price->model_year,
        'available_count' => $cars->count(),
        'day_price' => $daily,
        'day_company_price' => $daily,
        'total_days' => $days,
        'image_id' => $main?->id,
        'image_price_id' => $imagePrice->id,
        'company_name' => $car->company?->name
      ];
    }
    // Display one representative row per car specification (latest pricing record wins).
    $rows = collect($rows)->unique(fn($x) => $x['brand'] . '|' . $x['model'] . '|' . $x['model_year'])->values()->all();
    return response()->json(['data' => $rows, 'meta' => ['total_days' => $days, 'count' => count($rows)]]);
  }
  public function store(BookingRentRequest $r): JsonResponse
  {
    $d = $r->validated();
    $this->ensureEligibleClient($d);
    [$start, $end, $days] = $this->duration($d);
    $booking = DB::transaction(function () use ($d, $start, $end, $days) {
      $price = CarRentalPrice::query()->lockForUpdate()->findOrFail($d['car_rental_price_id']);
      if (
        $d['client_type'] === 'customer' && ($price->client_type !== 'customer' || $price->company_id !== null)
        || $d['client_type'] === 'company' && ($price->client_type !== 'company' || (int)$price->company_id !== (int)$d['customer_company_id'])
      )
        throw ValidationException::withMessages(['car_rental_price_id' => ['Pricing does not belong to the selected client.']]);
      $daily = $this->rate($price, $start, $end, $days);
      if ($daily === null) throw ValidationException::withMessages(['car_rental_price_id' => ['No pricing interval covers the rental.']]);
      if ($this->availableVehicles($price, $start, $end, true)->isEmpty())
        throw ValidationException::withMessages(['car_rental_price_id' => ['No unreserved, unsold vehicle is available.']]);
      $pickup = Branch::findOrFail($d['pick_up_branch_id']);
      $return = Branch::findOrFail($d['return_branch_id']);
      $branchFee = (int)$pickup->id === (int)$return->id ? 0 : 5;
      $ids = $d['extra_item_ids'] ?? [];
      $items = ExtraItem::whereIn('id', $ids)->get();
      $extra = 0;
      $sync = [];
      foreach ($items as $item) {
        $line = round((float)$item->price * ($item->is_daily ? $days : 1), 2);
        $extra += $line;
        $sync[$item->id] = ['unit_price' => $item->price, 'is_daily' => (bool)$item->is_daily, 'line_total' => $line];
      }
      $extra = round($extra, 2);
      $settings = $this->settings();
      $total = round($daily * $days + $extra + $branchFee, 2);
      $booking = Booking::create([
        'customer_id' => $d['client_type'] === 'customer' ? $d['customer_id'] : null,
        'customer_company_id' => $d['client_type'] === 'company' ? $d['customer_company_id'] : null,
        'company_id' => $this->availableVehicles($price, $start, $end)->first()->company_id,
        'car_rental_price_id' => $price->id,
        'car_id' => null,
        'service' => 'rent',
        'day_price' => $daily,
        'day_company_price' => $daily,
        'total_days' => $days,
        'extra_charge' => $extra,
        'extra_company_charge' => $extra,
        'extra_km_price' => 0,
        'tax' => 0,
        'currency' => $settings['currency'],
        'is_relayed' => false,
        'relayed_date' => null,
        'status' => 'pending',
        'ticket_number' => null,
        'total' => $total,
        'company_total' => $total,
        'branch_fee' => $branchFee,
        'start_date' => $d['start_date'],
        'start_time' => $d['start_time'],
        'end_date' => $d['end_date'],
        'end_time' => $d['end_time'],
        'pick_up_location' => $pickup->name,
        'return_location' => $return->name,
        'payment_method' => 'cash',
        'is_taxable' => false
      ]);
      $booking->extraItems()->sync($sync);
      return $booking;
    }, 3);
    return response()->json(['data' => $booking->load(['rentalPrice.brand', 'rentalPrice.model', 'extraItems'])], 201);
  }

  /** Opens a dated availability report: customer/base or one customer's company-specific prices only. */
  public function availabilityReport(Request $request): \Illuminate\Http\Response
  {
    $data = $request->validate([
      'client_type' => ['required', 'in:customer,company'],
      'customer_company_id' => ['required_if:client_type,company', 'nullable', 'integer', 'exists:customer_companies,id'],
      'start_date' => ['required', 'date_format:Y-m-d'],
      'start_time' => ['required', 'date_format:H:i'],
      'end_date' => ['required', 'date_format:Y-m-d'],
      'end_time' => ['required', 'date_format:H:i'],
    ]);
    if ($data['client_type'] === 'company') {
      $this->ensureEligibleClient($data);
    }
    [$start, $end, $days] = $this->duration($data);
    $companyName = null;
    if ($data['client_type'] === 'company') {
      $c = CustomerCompany::findOrFail($data['customer_company_id']);
      $companyName = $c->company_name ?? $c->name ?? ('Company #' . $c->id);
    }
    $rows = collect();
    $seen = [];
    foreach ($this->matchingPrices($data)->orderByDesc('id')->get() as $price) {
      $day = $this->rate($price, $start, $end, $days);
      if ($day === null) continue;
      foreach ($this->availableVehicles($price, $start, $end) as $car) {
        if (isset($seen[$car->id])) continue;
        $seen[$car->id] = true;
        $car->loadMissing(['brand:id,name', 'model:id,name', 'company:id,name', 'branch:id,name']);
        $rows->push([
          'number' => $car->number,
          'brand' => $car->brand?->name ?? '-',
          'model' => $car->model?->name ?? '-',
          'year' => $car->model_year,
          'owner' => $car->company?->name ?? '-',
          'location' => $car->branch?->name ?: ($car->current_location ?: 'Unknown'),
          'day_price' => $day
        ]);
      }
    }
    $groups = $rows->sortBy('number')->groupBy('location')->sortKeys();
    if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) abort(500, 'Install barryvdh/laravel-dompdf.');
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rent.booking-available-cars-report', [
      'groups' => $groups,
      'total' => $rows->count(),
      'days' => $days,
      'start' => $start,
      'end' => $end,
      'companyName' => $companyName,
      'clientType' => $data['client_type'],
      'currency' => $this->settings()['currency'],
    ])->setPaper('a4', 'landscape');
    return $pdf->stream('booking-available-cars.pdf');
  }
  public function index(Request $r): JsonResponse
  {
    $d = $r->validate([
      'tab' => ['nullable', 'in:pending,reservations'],
      'search' => ['nullable', 'string', 'max:120'],
      'per_page' => ['nullable', 'integer', 'between:1,100'],
      'page' => ['nullable', 'integer', 'min:1']
    ]);
    $q = Booking::with(['customer', 'customerCompany', 'rentalPrice.brand', 'rentalPrice.model', 'car.brand', 'car.model'])->where('service', 'rent')
      ->whereIn('status', ($d['tab'] ?? 'pending') === 'pending' ? ['pending', 'online_pending'] : ['approved']);
    if (!empty($d['search'])) {
      $term = '%' . $d['search'] . '%';
      $q->where(fn($x) => $x->where('id', 'like', $term)->orWhereHas('car', fn($v) => $v->where('number', 'like', $term)));
    }
    return response()->json($q->orderByDesc('id')->paginate($d['per_page'] ?? 15));
  }
  public function reject(Booking $booking): JsonResponse
  {
    abort_unless($booking->service === 'rent', 404);
    $updated = DB::transaction(function () use ($booking) {
      $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
      if (!in_array($locked->status, ['pending', 'online_pending'], true))
        throw ValidationException::withMessages(['status' => ['Only pending or online-pending bookings can be rejected.']]);
      if ($locked->reservation()->exists() || $locked->car_id !== null)
        throw ValidationException::withMessages(['status' => ['Cannot reject a booking already assigned to a car.']]);
      $locked->status = 'rejected';
      $locked->save();
      return $locked;
    }, 3);
    return response()->json(['message' => 'Booking rejected.', 'data' => ['id' => $updated->id, 'status' => $updated->status]]);
  }
  public function show(Booking $booking): JsonResponse
  {
    abort_unless($booking->service === 'rent', 404);
    return response()->json(['data' => $booking->load(['customer', 'customerCompany', 'rentalPrice.brand', 'rentalPrice.model', 'car', 'reservation.car', 'extraItems'])]);
  }
}
