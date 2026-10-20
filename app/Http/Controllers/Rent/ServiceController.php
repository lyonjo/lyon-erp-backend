<?php
namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\ServiceRequest;
use App\Models\Rent\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $query = Service::query()->withCount('suppliers');
        if ($search !== '') $query->where('name', 'like', '%'.$search.'%');
        $page = $query->orderByDesc('id')->paginate(max(5, min(100, (int) $request->input('per_page', 15))));
        return response()->json(['data' => $page->items(), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function options()
    {
        return response()->json(['data' => Service::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function show(Service $service)
    {
        return response()->json(['data' => $service->loadCount('suppliers')]);
    }

    public function store(ServiceRequest $request)
    {
        return response()->json(['data' => Service::create($request->validated())], 201);
    }

    public function update(ServiceRequest $request, Service $service)
    {
        $service->update($request->validated());
        return response()->json(['data' => $service->fresh()]);
    }

    public function destroy(Service $service)
    {
        // Pivot rows are removed by the migration's cascading FK on service_supplier.
        // Existing suppliers are not deleted.
        $service->delete();
        return response()->noContent();
    }
}
