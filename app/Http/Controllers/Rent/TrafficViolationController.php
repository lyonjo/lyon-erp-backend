<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Booking, CarReservation, RentalContract, TrafficViolation, Vehicle};
use Carbon\Carbon;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class TrafficViolationController extends Controller
{
    private function relations(): array
    {
        return ['car.brand', 'car.model', 'contract.booking.customer', 'contract.booking.customerCompany', 'driver', 'image'];
    }

    private function present(TrafficViolation $row): array
    {
        $booking = $row->contract?->booking;
        $customer = $booking?->customer?->name ?? $booking?->customerCompany?->name ?? $row->manual_customer_name;
        return [
            'id' => $row->id,
            'car_id' => $row->car_id,
            'car_number' => $row->car?->number,
            'contract_id' => $row->contract_id,
            'driver_id' => $row->driver_id,
            'violation_date' => $row->violation_date?->format('Y-m-d'),
            'violation_time' => substr((string)$row->violation_time, 0, 5),
            'violation_no' => $row->violation_no,
            'violation_value' => $row->violation_value,
            'violation_reason' => $row->violation_reason,
            'manual_customer_name' => $row->manual_customer_name,
            'customer_name' => $customer ?? '—',
            'on' => 'customer',
            'has_image' => $row->image !== null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:120', 'per_page' => 'nullable|integer|min:1|max:100']);
        $q = TrafficViolation::with($this->relations());
        if ($term = trim((string)($data['search'] ?? ''))) {
            $q->where(function ($q) use ($term) {
                $q->where('violation_no', 'like', '%' . $term . '%')
                    ->orWhere('manual_customer_name', 'like', '%' . $term . '%')
                    ->orWhereHas('car', fn($v) => $v->where('number', 'like', '%' . $term . '%'))
                    ->orWhereHas('contract.booking.customer', fn($c) => $c->where('name', 'like', '%' . $term . '%'))
                    ->orWhereHas('contract.booking.customerCompany', fn($c) => $c->where('name', 'like', '%' . $term . '%'));
            });
        }
        $rows = $q->orderByDesc('id')->paginate((int)($data['per_page'] ?? 15));
        $rows->getCollection()->transform(fn($r) => $this->present($r));
        return response()->json($rows);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:120']);
        $q = Vehicle::query()->with(['brand:id,name', 'model:id,name'])->select(['id', 'number', 'brand_id', 'model_id', 'model_year']);
        if ($term = trim((string)($data['search'] ?? ''))) $q->where('number', 'like', '%' . $term . '%');
        return response()->json(['data' => $q->orderBy('number')->limit(60)->get()->map(fn($v) => [
            'id' => $v->id,
            'number' => $v->number,
            'label' => trim($v->number . ' — ' . ($v->brand?->name ?? '') . ' ' . ($v->model?->name ?? '') . ' ' . ($v->model_year ?? ''))
        ])]);
    }

    private function matchingBooking(int $vehicleId, string $date, string $time): ?Booking
    {
        $moment = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time)->format('Y-m-d H:i:s');
        // [pickup, dropoff): after scheduled return is not part of the rental.
        $reservation = CarReservation::query()->where('car_id', $vehicleId)
            ->where('pickup_start_datetime', '<=', $moment)->where('dropoff_end_datetime', '>', $moment)
            ->whereHas('booking', fn($q) => $q->whereNotIn('status', ['rejected']))
            ->with('booking.customer', 'booking.customerCompany')->orderByDesc('pickup_start_datetime')->first();
        return $reservation?->booking;
    }

    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'car_id' => 'required|integer|exists:vehicles,id',
            'violation_date' => 'required|date_format:Y-m-d',
            'violation_time' => 'required|date_format:H:i',
        ]);
        $booking = $this->matchingBooking((int)$data['car_id'], $data['violation_date'], $data['violation_time']);
        $contract = $booking ? RentalContract::where('booking_id', $booking->id)->first() : null;
        return response()->json(['data' => [
            'booking_id' => $booking?->id,
            'contract_id' => $contract?->id,
            'customer_name' => $booking?->customer?->name ?? $booking?->customerCompany?->name,
            'matched' => $booking !== null,
            'can_bill_contract' => $contract !== null,
            'message' => $booking && !$contract ? 'Booking exists at this time but has no contract yet.' : null,
        ]]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'car_id' => 'required|integer|exists:vehicles,id',
            'violation_date' => 'required|date_format:Y-m-d',
            'violation_time' => 'required|date_format:H:i',
            'violation_no' => 'required|string|max:100',
            'violation_value' => 'required|numeric|min:0|max:9999999999',
            'violation_reason' => 'nullable|string|max:4000',
            'manual_customer_name' => 'nullable|string|max:190',
            'image' => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);
    }

    private function save(Request $request, ?TrafficViolation $row): JsonResponse
    {
        $data = $this->validateData($request);
        $booking = $this->matchingBooking((int)$data['car_id'], $data['violation_date'], $data['violation_time']);
        $contract = $booking ? RentalContract::where('booking_id', $booking->id)->first() : null;
        if ($booking && !$contract) {
            throw ValidationException::withMessages(['violation_date' => ['A booking matches this time but has no contract. Create its contract before billing a violation.']]);
        }
        if (!$booking && empty(trim((string)($data['manual_customer_name'] ?? '')))) {
            throw ValidationException::withMessages(['manual_customer_name' => ['Enter a customer name when no contract matches the selected date and time.']]);
        }
        $storedPath = null;
        if ($request->hasFile('image')) $storedPath = $request->file('image')->store('traffic-violations', 'public');
        try {
            $oldPath = DB::transaction(function () use ($row, $data, $booking, $contract, $storedPath) {
                $values = collect($data)->except(['image'])->all();
                $values['contract_id'] = $contract?->id;
                $values['driver_id'] = null;
                $values['manual_customer_name'] = $contract ? null : trim((string)($values['manual_customer_name'] ?? ''));
                if (!$row) $row = TrafficViolation::create($values);
                else $row->update($values);
                $old = $row->image?->path;
                if ($storedPath) {
                    $row->image?->delete();
                    $row->image()->create(['path' => $storedPath, 'type' => 'traffic_violation', 'title' => 'Traffic Violation #' . $row->id, 'alt' => 'Traffic violation image']);
                }
                return ['id' => $row->id, 'old' => $storedPath ? $old : null];
            });
        } catch (\Throwable $e) {
            if ($storedPath) Storage::disk('public')->delete($storedPath);
            throw $e;
        }
        if ($oldPath['old']) Storage::disk('public')->delete($oldPath['old']);
        return response()->json(['data' => $this->present(TrafficViolation::with($this->relations())->findOrFail($oldPath['id']))], $row ? 200 : 201);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, null);
    }
    public function update(Request $request, TrafficViolation $violation): JsonResponse
    {
        return $this->save($request, $violation);
    }
    public function show(TrafficViolation $violation): JsonResponse
    {
        return response()->json(['data' => $this->present($violation->load($this->relations()))]);
    }
    public function destroy(TrafficViolation $violation): JsonResponse
    {
        $path = $violation->image?->path;
        DB::transaction(function () use ($violation) {
            $violation->image?->delete();
            $violation->delete();
        });
        if ($path) Storage::disk('public')->delete($path);
        return response()->json(['message' => 'Deleted']);
    }
    public function image(TrafficViolation $violation)
    {
        $img = $violation->image()->firstOrFail();
        abort_unless(Storage::disk('public')->exists($img->path), 404);
        return response()->file(Storage::disk('public')->path($img->path), ['Cache-Control' => 'private, no-store']);
    }
}
