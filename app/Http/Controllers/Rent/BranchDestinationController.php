<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\Branch;
use App\Models\Rent\BranchDestination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchDestinationController extends Controller
{
    public function index(Branch $branch)
    {
        return response()->json(['data' => $branch->destinations()
            ->with('destinationBranch:id,name')
            ->orderBy('id')->get(), 'branch' => $branch->only(['id', 'name'])]);
    }

    public function options(Branch $branch)
    {
        return response()->json(['data' => Branch::query()
            ->select('id', 'name')->where('id', '!=', $branch->id)->orderBy('name')->get()]);
    }

    public function store(Request $request, Branch $branch)
    {
        $data = $this->validated($request, $branch);
        $destination = $branch->destinations()->create($data);
        return response()->json(['data' => $destination->load('destinationBranch:id,name')], 201);
    }

    public function update(Request $request, Branch $branch, BranchDestination $destination)
    {
        $this->ensureOwner($branch, $destination);
        $destination->update($this->validated($request, $branch, $destination));
        return response()->json(['data' => $destination->fresh('destinationBranch:id,name')]);
    }

    public function destroy(Branch $branch, BranchDestination $destination)
    {
        $this->ensureOwner($branch, $destination);
        $destination->delete();
        return response()->noContent();
    }

    private function ensureOwner(Branch $branch, BranchDestination $destination): void
    {
        abort_unless((int) $destination->branch_id === (int) $branch->id, 404);
    }

    private function validated(Request $request, Branch $branch, ?BranchDestination $current = null): array
    {
        $data = $request->validate([
            'destination_branch_id' => [
                'required',
                'integer',
                'exists:branches,id',
                Rule::notIn([(int) $branch->id]),
                Rule::unique('branch_destinations', 'destination_branch_id')
                    ->where('branch_id', $branch->id)->ignore($current?->id),
            ],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);
        return $data;
    }
}
