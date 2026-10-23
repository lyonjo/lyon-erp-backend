<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Vehicle, Booking, CarReservation};
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarReservationController extends Controller
{
  private const BLOCKING = ['pending', 'online_pending', 'approved', 'realyed'];
  private function airport(string $location): bool
  {
    return mb_stripos($location, 'airport') !== false || mb_strpos($location, 'مطار') !== false;
  }
  private function bookingData(Booking $b): array
  {
    return [
      'id' => $b->id,
      'customer_name' => $b->customer?->name ?? $b->customer?->customer_name ?? $b->customerCompany?->name ?? $b->customerCompany?->company_name,
      'service' => $b->service,
      'start_date' => $b->start_date,
      'start_time' => $b->start_time,
      'end_date' => $b->end_date,
      'end_time' => $b->end_time,
      'pick_up_location' => $b->pick_up_location,
      'return_location' => $b->return_location,
      'total_days' => $b->total_days,
      'total' => $b->total,
      'currency' => $b->currency,
      'ticket_number' => $b->ticket_number,
      'requires_ticket' => $this->airport((string)$b->pick_up_location)
    ];
  }
  public function index(Request $r): JsonResponse
  {
    $d = $r->validate(['search' => ['nullable', 'string', 'max:120'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);
    $q = Vehicle::with(['brand:id,name', 'model:id,name'])
      ->whereNotIn('id', DB::table('sold_cars')->select('vehicle_id'))
      ->orderBy('id');
    if (!empty($d['search'])) {
      $term = '%' . $d['search'] . '%';
      $q->where(fn($x) => $x->where('number', 'like', $term)->orWhereHas('brand', fn($b) => $b->where('name', 'like', $term))->orWhereHas('model', fn($b) => $b->where('name', 'like', $term)));
    }
    return response()->json($q->paginate($d['per_page'] ?? 15));
  }
  public function show(Vehicle $car): JsonResponse
  {
    abort_if(DB::table('sold_cars')->where('vehicle_id', $car->id)->exists(), 404);
    $rows = CarReservation::with(['booking.customer', 'booking.customerCompany'])->where('car_id', $car->id)
      ->whereHas('booking', fn($q) => $q->where('service', 'rent'))->orderBy('pickup_start_datetime')->get()
      ->map(fn($x) => [
        'id' => $x->id,
        'booking_id' => $x->booking_id,
        'booking' => $this->bookingData($x->booking),
        'pickup_start_datetime' => $x->pickup_start_datetime,
        'dropoff_end_datetime' => $x->dropoff_end_datetime
      ]);
    return response()->json(['data' => ['car' => $car->load('brand', 'model'), 'reservations' => $rows]]);
  }
  public function bookingOptions(Vehicle $car, Request $r): JsonResponse
  {
    abort_if(DB::table('sold_cars')->where('vehicle_id', $car->id)->exists(), 404);
    $q = Booking::with(['customer', 'customerCompany', 'rentalPrice'])->where('service', 'rent')
      ->whereIn('status', self::BLOCKING)->whereDoesntHave('reservation')
      ->whereHas('rentalPrice', fn($p) => $p->where('brand_id', $car->brand_id)->where('model_id', $car->model_id)->where('model_year', $car->model_year));
    return response()->json(['data' => $q->orderByDesc('id')->limit(250)->get()->map(fn($b) => $this->bookingData($b))]);
  }
  public function assign(Vehicle $car, Request $r): JsonResponse
  {
    $data = $r->validate([
      'booking_id' => ['required', 'integer', 'exists:bookings,id'],
      'ticket_number' => ['nullable', 'string', 'max:120']
    ]);
    $record = DB::transaction(function () use ($car, $data) {
      $vehicle = Vehicle::whereKey($car->id)->lockForUpdate()->firstOrFail();
      if (!(int)$vehicle->status || DB::table('sold_cars')->where('vehicle_id', $vehicle->id)->exists())
        throw ValidationException::withMessages(['car_id' => ['This vehicle is sold or inactive.']]);
      $booking = Booking::with('rentalPrice')->whereKey($data['booking_id'])->lockForUpdate()->firstOrFail();
      if ($booking->status === 'rejected' || $booking->service !== 'rent' || !in_array($booking->status, self::BLOCKING, true))
        throw ValidationException::withMessages(['booking_id' => ['Booking is not assignable.']]);
      $p = $booking->rentalPrice;
      if (!$p || $p->brand_id != $vehicle->brand_id || $p->model_id != $vehicle->model_id || $p->model_year != $vehicle->model_year)
        throw ValidationException::withMessages(['booking_id' => ['Booking car category does not match this vehicle.']]);
      if ($booking->reservation()->exists()) throw ValidationException::withMessages(['booking_id' => ['This booking is already assigned.']]);
      $start = CarbonImmutable::parse($booking->start_date . ' ' . $booking->start_time);
      $end = CarbonImmutable::parse($booking->end_date . ' ' . $booking->end_time);
      $overlap = CarReservation::where('car_id', $vehicle->id)->where('pickup_start_datetime', '<', $end)
        ->where('dropoff_end_datetime', '>', $start)->whereHas('booking', fn($q) => $q->whereIn('status', self::BLOCKING))->exists();
      if ($overlap) throw ValidationException::withMessages(['car_id' => ['Vehicle is already reserved for the requested period.']]);
      $reservation = CarReservation::create([
        'car_id' => $vehicle->id,
        'booking_id' => $booking->id,
        'pickup_start_datetime' => $start,
        'dropoff_end_datetime' => $end
      ]);
      $booking->car_id = $vehicle->id;
      $booking->company_id = $vehicle->company_id;
      $booking->status = 'approved';
      if ($this->airport((string)$booking->pick_up_location)) $booking->ticket_number = $data['ticket_number'] ?? null;
      $booking->save();
      return $reservation;
    }, 3);
    return response()->json(['data' => $record], 201);
  }
  public function update(Vehicle $car, CarReservation $reservation, Request $r): JsonResponse
  {
    abort_unless((int)$reservation->car_id === (int)$car->id, 404);
    $d = $r->validate([
      'booking_id' => ['required', 'integer', 'exists:bookings,id'],
      'ticket_number' => ['nullable', 'string', 'max:120']
    ]);
    // Reassign through the same validated, concurrency-safe create path after removing old reservation.
    // Lock both bookings and vehicle consistently; rollback the deletion if assignment fails.
    return DB::transaction(function () use ($car, $reservation, $d) {
      $old = Booking::findOrFail($reservation->booking_id);
      $reservation->delete();
      $old->car_id = null;
      if ($old->status === 'approved') $old->status = 'pending';
      $old->save();
      // Nested transaction joins outer transaction; if assignment fails the deletion rolls back.
      $response = $this->assign($car, new Request($d));
      return response()->json(['data' => $response->getData(true)['data']]);
    }, 3);
  }
  public function destroy(Vehicle $car, CarReservation $reservation): JsonResponse
  {
    abort_unless((int)$reservation->car_id === (int)$car->id, 404);
    DB::transaction(function () use ($reservation) {
      $b = Booking::whereKey($reservation->booking_id)->lockForUpdate()->first();
      $reservation->delete();
      if ($b) {
        $b->car_id = null;
        $b->ticket_number = null;
        if ($b->status === 'approved') $b->status = 'pending';
        $b->save();
      }
    });
    return response()->json(['message' => 'Reservation removed. Booking retained.']);
  }
}
