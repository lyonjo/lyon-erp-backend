<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\VehicleSaveRequest;
use App\Models\Rent\Branch;
use App\Models\Rent\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleManagementController extends Controller
{
    private function query()
    {
        return Vehicle::with([
            'brand:id,name',
            'model:id,name',
            'company:id,name',
            'branch:id,name',
            'features:id,name',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $this->query()->orderByDesc('id');
        $search = trim($input['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('vin_number', 'like', "%{$search}%")
                    ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('model', fn($m) => $m->where('name', 'like', "%{$search}%"));
            });
        }

        $page = $query->paginate((int) ($input['per_page'] ?? 15));

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return response()->json([
            'data' => $vehicle->load(['brand', 'model', 'company', 'branch', 'features']),
        ]);
    }

    public function store(VehicleSaveRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['features']);
        $data['current_location'] = Branch::findOrFail($data['branch_id'])->name;

        $vehicle = DB::transaction(function () use ($data, $request) {
            $vehicle = Vehicle::create($data);
            $vehicle->features()->sync($request->validated('features', []));
            return $vehicle;
        });

        return response()->json([
            'data' => $vehicle->load(['brand', 'model', 'company', 'branch', 'features']),
        ], 201);
    }

    public function update(VehicleSaveRequest $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->safe()->except(['features']);
        $data['current_location'] = Branch::findOrFail($data['branch_id'])->name;

        DB::transaction(function () use ($vehicle, $data, $request) {
            $vehicle->update($data);
            $vehicle->features()->sync($request->validated('features', []));
        });

        return response()->json([
            'data' => $vehicle->fresh()->load(['brand', 'model', 'company', 'branch', 'features']),
        ]);
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        DB::transaction(function () use ($vehicle) {
            $vehicle->features()->detach();
            $vehicle->delete();
        });

        return response()->json(['message' => 'Vehicle deleted']);
    }
}
