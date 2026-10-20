<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\DriverRequest;
use App\Models\Rent\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:active,inactive'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Driver::query();
        $search = trim($data['search'] ?? '');
        if ($search !== '') {
            $term = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('license_number', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status'] === 'active');
        }

        $rows = $query->orderByDesc('id')->paginate((int) ($data['per_page'] ?? 15));

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function show(Driver $driver): JsonResponse
    {
        return response()->json(['data' => $driver]);
    }

    public function store(DriverRequest $request): JsonResponse
    {
        $driver = Driver::create($request->validated());
        return response()->json(['data' => $driver], 201);
    }

    public function update(DriverRequest $request, Driver $driver): JsonResponse
    {
        $driver->update($request->validated());
        return response()->json(['data' => $driver->refresh()]);
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $driver->delete();
        return response()->json(['message' => 'Driver deleted successfully.']);
    }
}
