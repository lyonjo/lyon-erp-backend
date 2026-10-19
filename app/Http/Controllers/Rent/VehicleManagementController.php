<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\VehicleSaveRequest;
use App\Models\Rent\{Vehicle, Branch, Image};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class VehicleManagementController extends Controller
{
    private function query()
    {
        return Vehicle::with(['brand:id,name', 'model:id,name', 'company:id,name', 'branch:id,name', 'features:id,name', 'images:id,imageable_id,imageable_type,type,path']);
    }
    private function shape(Vehicle $vehicle): array
    {
        $v = $vehicle->toArray();
        $v['main_image_id'] = $vehicle->images->firstWhere('type', 'main')?->id;
        $v['gallery_image_ids'] = $vehicle->images->where('type', 'gallery')->pluck('id')->values();
        return $v;
    }
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|min:5|max:100', 'page' => 'nullable|integer|min:1']);
        $q = $this->query()->orderByDesc('id');
        if ($s = trim($input['search'] ?? '')) $q->where(fn($query) => $query->where('number', 'like', "%{$s}%")->orWhere('vin_number', 'like', "%{$s}%")->orWhereHas('brand', fn($x) => $x->where('name', 'like', "%{$s}%"))->orWhereHas('model', fn($x) => $x->where('name', 'like', "%{$s}%")));
        $p = $q->paginate((int)($input['per_page'] ?? 15));
        return response()->json(['data' => collect($p->items())->map(fn($v) => $this->shape($v)), 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()]]);
    }
    public function show(Vehicle $vehicle): JsonResponse
    {
        return response()->json(['data' => $this->shape($vehicle->load(['brand', 'model', 'company', 'branch', 'features', 'images']))]);
    }
    public function store(VehicleSaveRequest $request): JsonResponse
    {
        $values = $request->safe()->except(['features', 'gallery', 'main_image', 'delete_image_ids']);
        $values['current_location'] = Branch::findOrFail($values['branch_id'])->name;
        $vehicle = DB::transaction(function () use ($values, $request) {
            $v = Vehicle::create($values);
            $v->features()->sync($request->input('features', []));
            return $v;
        });
        $this->saveImages($vehicle, $request);
        return response()->json(['data' => $this->shape($vehicle->load(['brand', 'model', 'company', 'branch', 'features', 'images']))], 201);
    }
    public function update(VehicleSaveRequest $request, Vehicle $vehicle): JsonResponse
    {
        $values = $request->safe()->except(['features', 'gallery', 'main_image', 'delete_image_ids']);
        $values['current_location'] = Branch::findOrFail($values['branch_id'])->name;
        DB::transaction(function () use ($vehicle, $values, $request) {
            $vehicle->update($values);
            $vehicle->features()->sync($request->input('features', []));
        });
        $this->deleteImages($vehicle, $request->input('delete_image_ids', []));
        $this->saveImages($vehicle, $request);
        return response()->json(['data' => $this->shape($vehicle->fresh()->load(['brand', 'model', 'company', 'branch', 'features', 'images']))]);
    }
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        foreach ($vehicle->images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
        $vehicle->features()->detach();
        $vehicle->delete();
        return response()->json(['message' => 'Vehicle deleted']);
    }
    private function deleteImages(Vehicle $vehicle, array $ids): void
    {
        foreach ($vehicle->images()->whereIn('id', $ids)->get() as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }
    private function saveImages(Vehicle $vehicle, VehicleSaveRequest $request): void
    {
        if ($file = $request->file('main_image')) {
            $this->deleteImages($vehicle, $vehicle->images()->where('type', 'main')->pluck('id')->all());
            $vehicle->images()->create(['path' => $file->store("vehicles/{$vehicle->id}", 'public'), 'type' => 'main', 'title' => 'Vehicle main image', 'alt' => 'Vehicle']);
        }
        foreach ($request->file('gallery', []) as $file) $vehicle->images()->create(['path' => $file->store("vehicles/{$vehicle->id}", 'public'), 'type' => 'gallery', 'title' => 'Vehicle gallery', 'alt' => 'Vehicle']);
    }
    public function image(Vehicle $vehicle, Image $image)
    {
        abort_unless($image->imageable_type === $vehicle->getMorphClass() && (int)$image->imageable_id === $vehicle->id, 404);
        return $this->serve($image->path);
    }
    public function serve(string $path)
    {
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 415);
        return $disk->response($path, null, ['Content-Type' => $mime, 'Content-Disposition' => 'inline', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
