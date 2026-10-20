<?php
namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\SupplierRequest;
use App\Models\Rent\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(5, min(100, (int) $request->input('per_page', 15)));
        $search = trim((string) $request->input('search', ''));
        $query = Supplier::query()->with('services:id,name');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('supplier_name', 'like', $like)
                    ->orWhere('phone_number', 'like', $like)
                    ->orWhere('representative_name', 'like', $like)
                    ->orWhereHas('services', fn ($s) => $s->where('name', 'like', $like));
            });
        }
        $page = $query->orderByDesc('id')->paginate($perPage);
        return response()->json(['data' => $page->items(), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function show(Supplier $supplier)
    {
        return response()->json(['data' => $supplier->load('services:id,name')]);
    }

    public function store(SupplierRequest $request)
    {
        $values = $request->validated();
        $serviceIds = $values['service_ids'] ?? [];
        unset($values['service_ids']);
        $supplier = DB::transaction(function () use ($values, $serviceIds) {
            $supplier = Supplier::create($values);
            $supplier->services()->sync($serviceIds);
            return $supplier;
        });
        return response()->json(['data' => $supplier->load('services:id,name')], 201);
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $values = $request->validated();
        $hasIds = array_key_exists('service_ids', $values);
        $ids = $values['service_ids'] ?? [];
        unset($values['service_ids']);
        DB::transaction(function () use ($supplier, $values, $hasIds, $ids) {
            $supplier->update($values);
            if ($hasIds) $supplier->services()->sync($ids);
        });
        return response()->json(['data' => $supplier->fresh()->load('services:id,name')]);
    }

    public function destroy(Supplier $supplier)
    {
        DB::transaction(function () use ($supplier) {
            $supplier->services()->detach();
            $supplier->delete();
        });
        return response()->noContent();
    }
}
