<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rent;

use App\Enums\MaintenanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\VehicleMaintenanceRequest;
use App\Models\Rent\Vehicle;
use App\Models\Rent\VehicleMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleMaintenanceController extends Controller
{
    private function formatted(VehicleMaintenance $record): array
    {
        $v = $record->vehicle;
        $service = $record->service_key;

        return [
            'id' => $record->id,
            'car_id' => $record->car_id,
            'vehicle_no' => $v?->number,
            'brand' => $v?->brand?->name,
            'model' => $v?->model?->name,
            'model_year' => $v?->model_year,
            'company_name' => $v?->company?->name,
            'service_key' => $service?->value,
            'service' => $service?->label(),
            'service_ar' => $service?->labelAr(),
            'date' => $record->date?->format('Y-m-d'),
            'price' => $record->price,
            'counter_km' => $record->counter_km,
            'details' => $record->details,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $size = min(100, max(5, (int) $request->query('per_page', 15)));
        $search = trim((string) $request->query('search', ''));
        $query = VehicleMaintenance::query()->with(['vehicle.brand', 'vehicle.model', 'vehicle.company']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('details', 'like', '%' . $search . '%')
                    ->orWhere('service_key', 'like', '%' . $search . '%')
                    ->orWhereHas('vehicle', function ($vq) use ($search) {
                        $vq->where('number', 'like', '%' . $search . '%')
                            ->orWhereHas('brand', fn($bq) => $bq->where('name', 'like', '%' . $search . '%'))
                            ->orWhereHas('model', fn($mq) => $mq->where('name', 'like', '%' . $search . '%'))
                            ->orWhereHas('company', fn($cq) => $cq->where('name', 'like', '%' . $search . '%'));
                    });
            });
        }

        $page = $query->orderByDesc('date')->orderByDesc('id')->paginate($size);

        return response()->json([
            'data' => $page->getCollection()->map(fn($row) => $this->formatted($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function services(): JsonResponse
    {
        return response()->json(['data' => array_map(fn($service) => [
            'key' => $service->value,
            'name' => $service->label(),
            'name_ar' => $service->labelAr(),
        ], MaintenanceService::cases())]);
    }

    public function vehicleOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $query = Vehicle::query()->with(['brand', 'model', 'company']);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', '%' . $search . '%')
                    ->orWhereHas('brand', fn($b) => $b->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('model', fn($m) => $m->where('name', 'like', '%' . $search . '%'));
            });
        }
        $rows = $query->orderByDesc('id')->limit(50)->get()->map(fn($v) => [
            'id' => $v->id,
            'number' => $v->number,
            'brand' => $v->brand?->name,
            'model' => $v->model?->name,
            'model_year' => $v->model_year,
            'company_name' => $v->company?->name,
            'current_km' => $v->current_km,
        ]);
        return response()->json(['data' => $rows]);
    }

    public function show(VehicleMaintenance $vehicleMaintenance): JsonResponse
    {
        return response()->json(['data' => $this->formatted($vehicleMaintenance->load(['vehicle.brand', 'vehicle.model', 'vehicle.company']))]);
    }

    public function store(VehicleMaintenanceRequest $request): JsonResponse
    {
        $record = VehicleMaintenance::create($request->validated());
        return response()->json(['data' => $this->formatted($record->load(['vehicle.brand', 'vehicle.model', 'vehicle.company']))], 201);
    }

    public function update(VehicleMaintenanceRequest $request, VehicleMaintenance $vehicleMaintenance): JsonResponse
    {
        $vehicleMaintenance->update($request->validated());
        return response()->json(['data' => $this->formatted($vehicleMaintenance->refresh()->load(['vehicle.brand', 'vehicle.model', 'vehicle.company']))]);
    }

    public function destroy(VehicleMaintenance $vehicleMaintenance): JsonResponse
    {
        $vehicleMaintenance->delete();
        return response()->json(['message' => 'Maintenance record deleted.']);
    }
}
