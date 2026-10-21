<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\ExtraItemRequest;
use App\Models\Rent\ExtraItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtraItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:190'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = ExtraItem::query();
        $term = trim($filters['search'] ?? '');
        if ($term !== '') {
            $query->where('name', 'like', '%' . addcslashes($term, '%_\\') . '%');
        }

        $page = $query->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 15));

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

    public function show(ExtraItem $extraItem): JsonResponse
    {
        return response()->json(['data' => $extraItem]);
    }

    public function store(ExtraItemRequest $request): JsonResponse
    {
        $extraItem = ExtraItem::create($request->validated());
        return response()->json(['data' => $extraItem], 201);
    }

    public function update(ExtraItemRequest $request, ExtraItem $extraItem): JsonResponse
    {
        $extraItem->update($request->validated());
        return response()->json(['data' => $extraItem->refresh()]);
    }

    public function destroy(ExtraItem $extraItem): JsonResponse
    {
        $extraItem->delete();
        return response()->json(['message' => 'Extra item deleted successfully.']);
    }
}
