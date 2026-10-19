<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\{VehicleBrand, VehicleModel, VehicleFeature, Image};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class VehicleCatalogController extends Controller
{
    private function cls(string $kind): string
    {
        return match ($kind) {
            'brands' => VehicleBrand::class,
            'models' => VehicleModel::class,
            'features' => VehicleFeature::class,
            default => abort(404)
        };
    }
    private function loadItems(string $kind)
    {
        $q = $this->cls($kind)::query();
        if ($kind === 'models') $q->with('brand:id,name');
        if ($kind === 'features') $q->with('image:id,imageable_id,imageable_type,path,type');
        return $q;
    }
    public function index(Request $r, string $kind): JsonResponse
    {
        $r->validate(['brand_id' => 'nullable|integer', 'search' => 'nullable|string|max:100']);
        $q = $this->loadItems($kind);
        if ($kind === 'models' && $r->filled('brand_id')) $q->where('brand_id', $r->integer('brand_id'));
        if ($r->filled('search')) $q->where('name', 'like', '%' . $r->query('search') . '%');
        return response()->json(['data' => $q->orderBy('name')->get()]);
    }
    private function rules(string $kind): array
    {
        $rules = ['name' => ['required', 'string', 'max:150']];
        if ($kind === 'models') $rules['brand_id'] = ['required', 'integer', 'exists:brands,id'];
        if ($kind === 'features') $rules['image'] = ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'];
        return $rules;
    }
    public function store(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate($this->rules($kind));
        unset($data['image']);
        $item = $this->cls($kind)::create($data);
        $this->saveFeatureImage($request, $kind, $item);
        return response()->json(['data' => $this->loadItems($kind)->findOrFail($item->id)], 201);
    }
    public function update(Request $request, string $kind, int $id): JsonResponse
    {
        $data = $request->validate($this->rules($kind));
        $item = $this->cls($kind)::findOrFail($id);
        unset($data['image']);
        $item->update($data);
        $this->saveFeatureImage($request, $kind, $item);
        return response()->json(['data' => $this->loadItems($kind)->findOrFail($item->id)]);
    }
    public function destroy(string $kind, int $id): JsonResponse
    {
        $item = $this->cls($kind)::findOrFail($id);
        if ($kind === 'brands' && ($item->models()->exists() || $item->vehicles()->exists())) return response()->json(['message' => 'Brand is in use'], 409);
        if ($kind === 'models' && $item->vehicles()->exists()) return response()->json(['message' => 'Model is in use'], 409);
        if ($kind === 'features' && $item->vehicles()->exists()) return response()->json(['message' => 'Feature is in use'], 409);
        if ($kind === 'features' && $item->image) {
            Storage::disk('public')->delete($item->image->path);
            $item->image->delete();
        }
        $item->delete();
        return response()->json(['message' => 'Deleted']);
    }
    private function saveFeatureImage(Request $r, string $kind, $item): void
    {
        if ($kind !== 'features' || !$r->hasFile('image')) return;
        $old = $item->image;
        if ($old) {
            Storage::disk('public')->delete($old->path);
            $old->delete();
        }
        $file = $r->file('image');
        $item->image()->create(['path' => $file->store("features/{$item->id}", 'public'), 'type' => 'feature', 'title' => $item->name, 'alt' => $item->name]);
    }
    public function featureImage(VehicleFeature $feature)
    {
        $image = $feature->image()->firstOrFail();
        return app(VehicleManagementController::class)->serve($image->path);
    }
}
