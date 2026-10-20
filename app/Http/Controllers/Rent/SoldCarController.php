<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\SoldCarRequest;
use App\Models\Rent\Company;
use App\Models\Rent\SoldCar;
use App\Models\Rent\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SoldCarController extends Controller
{
    private const WITH = ['vehicle.brand', 'vehicle.model', 'company'];

    private function format(SoldCar $sale): array
    {
        $vehicle = $sale->vehicle;
        return [
            'id' => $sale->id,
            'vehicle_id' => $sale->vehicle_id,
            'vehicle_no' => $vehicle?->number,
            'brand' => $vehicle?->brand?->name,
            'model' => $vehicle?->model?->name,
            'model_year' => $vehicle?->model_year,
            'company_id' => $sale->company_id,
            'company_name' => $sale->company?->name,
            'buyer_name' => $sale->buyer_name,
            'phone' => $sale->phone,
            'price' => $sale->price,
            'tax' => (bool) $sale->tax,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($data['search'] ?? '');
        $perPage = (int) ($data['per_page'] ?? 15);

        $query = SoldCar::query()->with(self::WITH);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $q->where('buyer_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhereHas('company', fn($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('vehicle', function ($v) use ($like) {
                        $v->where('number', 'like', $like)
                            ->orWhereHas('brand', fn($b) => $b->where('name', 'like', $like))
                            ->orWhereHas('model', fn($m) => $m->where('name', 'like', $like));
                    });
            });
        }
        $rows = $query->orderByDesc('id')->paginate($perPage);
        return response()->json([
            'data' => $rows->getCollection()->map(fn($row) => $this->format($row))->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function vehicleOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'include_id' => ['nullable', 'integer'],
        ]);
        $includeId = (int) ($data['include_id'] ?? 0);
        $query = Vehicle::query()->with(['brand', 'model'])
            ->where(function ($q) use ($includeId) {
                $q->whereDoesntHave('soldCar');
                if ($includeId > 0) $q->orWhereKey($includeId);
            });
        $search = trim($data['search'] ?? '');
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('number', 'like', $like)
                    ->orWhereHas('brand', fn($b) => $b->where('name', 'like', $like))
                    ->orWhereHas('model', fn($m) => $m->where('name', 'like', $like));
            });
        }
        return response()->json(['data' => $query->orderByDesc('id')->limit(50)->get()->map(fn($v) => [
            'id' => $v->id,
            'number' => $v->number,
            'brand' => $v->brand?->name,
            'model' => $v->model?->name,
        ])->values()]);
    }

    public function companyOptions(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $query = Company::query();
        $search = trim($data['search'] ?? '');
        if ($search !== '') $query->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%');
        return response()->json(['data' => $query->orderBy('name')->limit(50)->get(['id', 'name'])]);
    }

    public function show(SoldCar $soldCar): JsonResponse
    {
        return response()->json(['data' => $this->format($soldCar->load(self::WITH))]);
    }

    public function store(SoldCarRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['vehicle_id', 'company_id', 'buyer_name', 'phone', 'price']);
        $data['tax'] = false;
        try {
            $sale = DB::transaction(fn() => SoldCar::create($data));
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, [1062], true)) {
                throw ValidationException::withMessages(['vehicle_id' => ['This vehicle has already been sold.']]);
            }
            throw $e;
        }
        return response()->json(['data' => $this->format($sale->load(self::WITH))], 201);
    }

    public function update(SoldCarRequest $request, SoldCar $soldCar): JsonResponse
    {
        $data = $request->safe()->only(['vehicle_id', 'company_id', 'buyer_name', 'phone', 'price']);
        $data['tax'] = false;
        try {
            DB::transaction(fn() => $soldCar->update($data));
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, [1062], true)) {
                throw ValidationException::withMessages(['vehicle_id' => ['This vehicle has already been sold.']]);
            }
            throw $e;
        }
        return response()->json(['data' => $this->format($soldCar->refresh()->load(self::WITH))]);
    }

    public function destroy(SoldCar $soldCar): JsonResponse
    {
        $soldCar->delete();
        return response()->json(['message' => 'Sold vehicle record deleted.']);
    }
}
