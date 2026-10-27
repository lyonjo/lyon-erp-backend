<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{ExtraMoney, ExtraMoneyService, Booking, CarReservation, RentalContract, Vehicle, Lawsuit};
use Carbon\Carbon;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class ExtraMoneyController extends Controller
{
  private function relations(): array
  {
    return ['car.brand', 'car.model', 'contract.booking.customer', 'contract.booking.customerCompany', 'services', 'image', 'lawsuits'];
  }
  private function present(ExtraMoney $r): array
  {
    $booking = $r->contract?->booking;
    return [
      'id' => $r->id,
      'car_id' => $r->car_id,
      'car_number' => $r->car?->number,
      'contract_id' => $r->contract_id,
      'date' => $r->date?->format('Y-m-d'),
      'time' => substr((string)$r->time, 0, 5),
      'customer_name' => $booking?->customer?->name ?? $booking?->customerCompany?->name ?? $r->manual_customer_name ?? '—',
      'manual_customer_name' => $r->manual_customer_name,
      'has_croka' => (bool)$r->has_croka,
      'is_responsible' => $r->is_responsible,
      'croka_number' => $r->croka_number,
      'croka_amount' => $r->croka_amount,
      'value' => $r->value,
      'note' => $r->note,
      'has_image' => $r->image !== null,
      'service_ids' => $r->services->pluck('id')->all(),
      'services' => $r->services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'price' => $s->pivot->price, 'is_croka' => false]),
      'lawsuit_ids' => $r->lawsuits->pluck('id')->all()
    ];
  }
  public function index(Request $req): JsonResponse
  {
    $a = $req->validate(['search' => 'nullable|string|max:120', 'per_page' => 'nullable|integer|min:1|max:100']);
    $q = ExtraMoney::with($this->relations());
    if ($s = trim((string)($a['search'] ?? ''))) $q->where(function ($q) use ($s) {
      $q->where('manual_customer_name', 'like', "%$s%")
        ->orWhereHas('car', fn($v) => $v->where('number', 'like', "%$s%"))
        ->orWhereHas('contract.booking.customer', fn($c) => $c->where('name', 'like', "%$s%"))
        ->orWhereHas('contract.booking.customerCompany', fn($c) => $c->where('name', 'like', "%$s%"));
    });
    $rows = $q->orderByDesc('id')->paginate($a['per_page'] ?? 15);
    $rows->getCollection()->transform(fn($r) => $this->present($r));
    return response()->json($rows);
  }
  public function show(ExtraMoney $extraMoney): JsonResponse
  {
    return response()->json(['data' => $this->present($extraMoney->load($this->relations()))]);
  }
  public function vehicles(Request $req): JsonResponse
  {
    $a = $req->validate(['search' => 'nullable|string|max:100', 'include_id' => 'nullable|integer']);
    $includeId = (int)($a['include_id'] ?? 0);
    $q = Vehicle::query()->with(['brand:id,name', 'model:id,name'])
      ->where(function ($query) use ($includeId) {
        $query->whereNotIn('vehicles.id', function ($sub) {
          $sub->select('vehicle_id')->from('sold_cars')->whereNotNull('vehicle_id');
        });
        // Preserve the vehicle already linked to an existing record, even if sold later.
        if ($includeId > 0) $query->orWhere('vehicles.id', $includeId);
      });
    $search = trim((string)($a['search'] ?? ''));
    if ($search !== '') {
      // The selector may send its formatted label: "1234567 — Tesla Model-S".
      $numberSearch = trim(preg_split('/\s+[—–]\s+/u', $search, 2)[0]);
      $q->where('vehicles.number', 'like', '%' . $numberSearch . '%');
    }
    return response()->json(['data' => $q->orderBy('number')->limit(100)->get()->map(fn($v) => [
      'id' => $v->id,
      'number' => $v->number,
      'label' => trim($v->number . ' — ' . ($v->brand?->name ?? '') . ' ' . ($v->model?->name ?? '')),
    ])]);
  }
  private function bookingAt(int $car, string $date, string $time): ?Booking
  {
    $at = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time)->format('Y-m-d H:i:s');
    return CarReservation::where('car_id', $car)->where('pickup_start_datetime', '<=', $at)->where('dropoff_end_datetime', '>', $at)
      ->whereHas('booking', fn($q) => $q->whereNotIn('status', ['rejected']))->with('booking.customer', 'booking.customerCompany')
      ->latest('pickup_start_datetime')->first()?->booking;
  }
  public function resolve(Request $r): JsonResponse
  {
    $a = $r->validate(['car_id' => 'required|exists:vehicles,id', 'date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i']);
    $b = $this->bookingAt((int)$a['car_id'], $a['date'], $a['time']);
    $contract = $b ? RentalContract::where('booking_id', $b->id)->first() : null;
    return response()->json(['data' => ['matched' => (bool)$b, 'contract_id' => $contract?->id, 'customer_name' => $b?->customer?->name ?? $b?->customerCompany?->name, 'company_id' => $b?->company_id]]);
  }
  private function valid(Request $r): array
  {
    return $r->validate([
      'car_id' => 'required|integer|exists:vehicles,id',
      'date' => 'required|date_format:Y-m-d',
      'time' => 'required|date_format:H:i',
      'service_ids' => 'sometimes|array',
      'service_ids.*' => 'integer|distinct|exists:extra_money_services,id',
      'manual_customer_name' => 'nullable|string|max:190',
      'note' => 'nullable|string|max:4000',
      'has_croka' => 'nullable|boolean',
      'is_responsible' => 'nullable|boolean',
      'croka_number' => 'nullable|string|max:100',
      'croka_amount' => 'nullable|numeric|min:0|max:9999999',
      'image' => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:5120',
    ]);
  }
  private function save(Request $req, ?ExtraMoney $existing): JsonResponse
  {
    $a = $this->valid($req);
    $ids = $a['service_ids'] ?? [];
    $services = ExtraMoneyService::whereIn('id', $ids)->get();
    if ($services->contains(fn($svc) => mb_strtolower(trim($svc->name)) === 'croka')) throw ValidationException::withMessages(['service_ids' => ['Select the dedicated Croka option instead.']]);
    if ($services->count() !== count($ids)) throw ValidationException::withMessages(['service_ids' => ['Invalid service selection.']]);
    $hasCroka = (bool)($a['has_croka'] ?? false);
    $booking = $this->bookingAt((int)$a['car_id'], $a['date'], $a['time']);
    $contract = $booking ? RentalContract::where('booking_id', $booking->id)->first() : null;
    // Croka is independent from the service catalog. A lawsuit is created only when a contract is linked.
    if ($hasCroka && !array_key_exists('is_responsible', $a)) throw ValidationException::withMessages(['is_responsible' => ['Specify responsibility for an accident service.']]);
    if ($hasCroka && empty(trim((string)($a['croka_number'] ?? '')))) throw ValidationException::withMessages(['croka_number' => ['Croka number is required for lawsuit services.']]);
    if (!$booking && empty(trim((string)($a['manual_customer_name'] ?? '')))) throw ValidationException::withMessages(['manual_customer_name' => ['Enter the customer name if no reservation matches.']]);
    $crokaAmount = $hasCroka ? (float)($a['croka_amount'] ?? 0) : 0;
    if ($hasCroka && !array_key_exists('croka_amount', $a)) throw ValidationException::withMessages(['croka_amount' => ['Enter the Croka amount.']]);
    $value = round($services->sum(fn($s) => (float)$s->price) + $crokaAmount, 2);
    $newPath = $req->hasFile('image') ? $req->file('image')->store('extra-money', 'public') : null;
    try {
      $result = DB::transaction(function () use ($existing, $a, $services, $booking, $contract, $value, $hasCroka, $crokaAmount, $newPath) {
        $row = $existing ?: new ExtraMoney();
        $row->fill([
          'car_id' => $a['car_id'],
          'date' => $a['date'],
          'time' => $a['time'],
          'contract_id' => $contract?->id,
          'driver_id' => null,
          'value' => $value,
          'note' => $a['note'] ?? null,
          'manual_customer_name' => $booking ? null : ($a['manual_customer_name'] ?? null),
          'has_croka' => $hasCroka,
          'croka_amount' => $crokaAmount,
          'is_responsible' => $hasCroka ? ($a['is_responsible'] ?? null) : null,
          'croka_number' => $hasCroka ? ($a['croka_number'] ?? null) : null
        ]);
        $row->save();
        $row->services()->sync($services->mapWithKeys(fn($s) => [$s->id => ['price' => $s->price]])->all());
        // Croka creates a single associated lawsuit when the incident is linked to a rental contract.
        // Never create lawsuits for ordinary services; never duplicate an existing lawsuit on update.
        if ($hasCroka && $contract && $booking) {
          $lawsuit = $row->lawsuits()->orderBy('id')->first();
          if (!$lawsuit) {
            Lawsuit::create([
              'customer_id' => $booking->customer_id,
              'customer_company_id' => $booking->customer_company_id,
              'company_id' => $booking->company_id,
              'contract_id' => $contract->id,
              'extra_money_id' => $row->id,
              'vehicle_number' => (string)Vehicle::find($a['car_id'])?->number,
              'croka_number' => $a['croka_number'] ?? null,
              'type' => 'customer',
              'amount' => $crokaAmount,
              'fees' => 0,
              'date' => $a['date'],
              'status' => 'open',
              'customer_name' => $booking->customer?->name ?? $booking->customerCompany?->name,
              'note' => 'Automatically created from Extra Money Croka'
            ]);
          } elseif ($lawsuit->status === 'open') {
            $lawsuit->update(['croka_number' => $a['croka_number'] ?? null, 'amount' => $crokaAmount, 'date' => $a['date']]);
          }
        }
        $old = $row->image?->path;
        if ($newPath) {
          $row->image?->delete();
          $row->image()->create(['type' => 'extra_money', 'path' => $newPath, 'title' => 'Extra Money #' . $row->id, 'alt' => 'Extra money image']);
        }
        return ['id' => $row->id, 'old_path' => $newPath ? $old : null];
      });
    } catch (\Throwable $e) {
      if ($newPath) Storage::disk('public')->delete($newPath);
      throw $e;
    }
    if ($result['old_path']) Storage::disk('public')->delete($result['old_path']);
    return response()->json(['data' => $this->present(ExtraMoney::with($this->relations())->findOrFail($result['id']))], $existing ? 200 : 201);
  }
  public function store(Request $r): JsonResponse
  {
    return $this->save($r, null);
  }
  public function update(Request $r, ExtraMoney $extraMoney): JsonResponse
  {
    return $this->save($r, $extraMoney);
  }
  public function destroy(ExtraMoney $extraMoney): JsonResponse
  {
    if ($extraMoney->lawsuits()->where('status', 'closed')->exists()) throw ValidationException::withMessages(['record' => ['Cannot delete Extra Money with a closed lawsuit.']]);
    $path = $extraMoney->image?->path;
    DB::transaction(function () use ($extraMoney) {
      $extraMoney->lawsuits()->delete();
      $extraMoney->image?->delete();
      $extraMoney->services()->detach();
      $extraMoney->delete();
    });
    if ($path) Storage::disk('public')->delete($path);
    return response()->json(['message' => 'Deleted']);
  }
  public function image(ExtraMoney $extraMoney)
  {
    $i = $extraMoney->image()->firstOrFail();
    abort_unless(Storage::disk('public')->exists($i->path), 404);
    return response()->file(Storage::disk('public')->path($i->path), ['Cache-Control' => 'private,no-store']);
  }
}
