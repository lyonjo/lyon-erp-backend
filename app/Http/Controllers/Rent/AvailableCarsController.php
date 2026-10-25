<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{Branch, Company, Vehicle};
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request, Response};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class AvailableCarsController extends Controller
{
    /** An active vehicle must have a customer/base price effective now, and no blocking reservation. */
    private function availableQuery(CarbonImmutable $now): \Illuminate\Database\Eloquent\Builder
    {
        $instant = $now->format('Y-m-d H:i:s');
        $date = $now->toDateString();

        return Vehicle::query()
            ->where('vehicles.status', 1)
            ->whereNotIn('vehicles.id', DB::table('sold_cars')->select('vehicle_id'))
            ->whereExists(function ($query) use ($date) {
                $query->selectRaw('1')
                    ->from('car_rental_prices as crp')
                    ->join('pricing_periods as pp', 'pp.car_rental_price_id', '=', 'crp.id')
                    ->whereColumn('crp.brand_id', 'vehicles.brand_id')
                    ->whereColumn('crp.model_id', 'vehicles.model_id')
                    ->whereColumn('crp.model_year', 'vehicles.model_year')
                    ->where('crp.client_type', 'customer')
                    ->whereNull('crp.company_id')
                    ->whereDate('pp.start_date', '<=', $date)
                    ->whereDate('pp.end_date', '>=', $date);
            })
            ->whereNotExists(function ($query) use ($instant) {
                $query->selectRaw('1')
                    ->from('car_reservations as cr')
                    ->join('bookings as b', 'b.id', '=', 'cr.booking_id')
                    ->whereColumn('cr.car_id', 'vehicles.id')
                    ->whereIn('b.status', ['pending', 'online_pending', 'approved', 'realyed'])
                    ->where('cr.pickup_start_datetime', '<=', $instant)
                    ->where('cr.dropoff_end_datetime', '>', $instant);
            });
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone', 'Asia/Amman'));
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
    }

    private function filteredQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->availableQuery($this->now())
            ->with(['brand:id,name', 'model:id,name', 'company:id,name', 'branch:id,name']);
        if (!empty($filters['company_id'])) $query->where('vehicles.company_id', $filters['company_id']);
        if (!empty($filters['branch_id'])) $query->where('vehicles.branch_id', $filters['branch_id']);
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($like) {
                $q->where('vehicles.number', 'like', $like)
                    ->orWhereHas('brand', fn($b) => $b->where('name', 'like', $like))
                    ->orWhereHas('model', fn($m) => $m->where('name', 'like', $like))
                    ->orWhereHas('branch', fn($b) => $b->where('name', 'like', $like));
            });
        }
        return $query->orderBy('vehicles.number')->orderBy('vehicles.id');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $results = $this->filteredQuery($filters)->paginate($filters['per_page'] ?? 15);
        return response()->json($results);
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'companies' => Company::query()->select('id', 'name')->orderBy('name')->get(),
            'branches' => Branch::query()->select('id', 'name')->orderBy('name')->get(),
        ]]);
    }

    public function transfer(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']]);
        $updated = DB::transaction(function () use ($vehicle, $data) {
            $car = Vehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            if (!$this->availableQuery($this->now())->whereKey($car->id)->exists()) {
                throw ValidationException::withMessages(['vehicle' => ['This vehicle is no longer available for transfer.']]);
            }
            $branch = Branch::query()->findOrFail($data['branch_id']);
            $car->branch_id = $branch->id;
            $car->current_location = $branch->name;
            $car->save();
            return $car->load(['brand:id,name', 'model:id,name', 'company:id,name', 'branch:id,name']);
        }, 3);
        return response()->json(['data' => $updated, 'message' => 'Car location updated.']);
    }

    private function companyLogoData(Company $company): ?string
    {
        $company->loadMissing('logo');
        $path = $company->logo?->path;
        if (!$path || !Storage::disk('public')->exists($path)) return null;
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
        // Explicit allowlist: SVG cannot be embedded safely as arbitrary user-supplied markup.
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return null;
        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
    }

    public function report(Request $request): Response
    {
        $filters = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $company = Company::with('logo')->findOrFail($filters['company_id']);
        $branch = !empty($filters['branch_id']) ? Branch::findOrFail($filters['branch_id']) : null;
        $vehicles = $this->filteredQuery($filters)->get();
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'PDF renderer not installed. Run composer require barryvdh/laravel-dompdf.');
        }
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rent.available-cars-report', [
            'company' => $company,
            'branch' => $branch,
            'vehicles' => $vehicles,
            'generatedAt' => $this->now(),
            'logoData' => $this->companyLogoData($company),
        ])->setPaper('a4', 'portrait');
        return $pdf->stream('available-cars-' . $company->id . '-' . $this->now()->format('Ymd-His') . '.pdf');
    }
}
