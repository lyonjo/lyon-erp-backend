<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\BranchRequest;
use App\Models\Rent\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Branch::query()->orderByDesc('id');
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        $result = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $result->items(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function show(Branch $branch): JsonResponse
    {
        return response()->json(['data' => $branch]);
    }

    public function store(BranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        return response()->json(['data' => $branch, 'message' => 'Branch created successfully.'], 201);
    }

    public function update(BranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return response()->json(['data' => $branch->refresh(), 'message' => 'Branch updated successfully.']);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully.']);
    }
}
