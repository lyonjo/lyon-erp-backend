<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\ExternalVehicleRequest;
use App\Models\Rent\Branch;
use App\Models\Rent\ExternalVehicle;
use App\Models\Rent\Supplier;
use App\Models\Rent\VehicleBrand;
use App\Models\Rent\VehicleModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $search = trim((string) $request->query('search', ''));
        $query = ExternalVehicle::query()->with(['supplier:id,supplier_name', 'brand:id,name', 'model:id,name', 'branch:id,name']);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('vin_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('supplier_name', 'like', "%{$search}%"))
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('model', fn ($m) => $m->where('name', 'like', "%{$search}%"));
            });
        }
        $paginator = $query->orderByDesc('id')->paginate($perPage);
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $brands = VehicleBrand::query()->orderBy('name')->get(['id', 'name']);
        $models = VehicleModel::query()->orderBy('name')->get(['id', 'brand_id', 'name']);
        $suppliers = Supplier::query()
            ->whereHas('services', function ($serviceQuery) {
                $serviceQuery->whereRaw('LOWER(TRIM(name)) IN (?, ?)', [
                    'rent car',
                    'تأجير السيارات',
                ]);
            })
            ->when($search !== '', fn ($q) => $q->where('supplier_name', 'like', "%{$search}%"))
            ->orderBy('supplier_name')->get(['id', 'supplier_name']);
        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
        return response()->json(['data' => compact('brands', 'models', 'suppliers', 'branches')]);
    }

    public function show(ExternalVehicle $externalVehicle): JsonResponse
    {
        return response()->json(['data' => $externalVehicle->load(['supplier:id,supplier_name', 'brand:id,name', 'model:id,name', 'branch:id,name'])]);
    }

    public function store(ExternalVehicleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['current_location'] = Branch::query()->findOrFail($data['branch_id'])->name;
        $vehicle = ExternalVehicle::create($data);
        return response()->json(['data' => $vehicle->load(['supplier:id,supplier_name', 'brand:id,name', 'model:id,name', 'branch:id,name'])], 201);
    }

    public function update(ExternalVehicleRequest $request, ExternalVehicle $externalVehicle): JsonResponse
    {
        $data = $request->validated();
        // Preserve current_location when changing other fields; update on branch change.
        if ((int) $data['branch_id'] !== (int) $externalVehicle->branch_id) {
            $data['current_location'] = Branch::query()->findOrFail($data['branch_id'])->name;
        }
        $externalVehicle->update($data);
        return response()->json(['data' => $externalVehicle->fresh()->load(['supplier:id,supplier_name', 'brand:id,name', 'model:id,name', 'branch:id,name'])]);
    }

    public function destroy(ExternalVehicle $externalVehicle): JsonResponse
    {
        $externalVehicle->delete();
        return response()->json(['message' => 'External vehicle deleted.']);
    }
}
