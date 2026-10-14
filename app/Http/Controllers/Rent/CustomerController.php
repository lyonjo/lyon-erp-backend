<?php

namespace App\Http\Controllers\Rent;

use App\Http\Resources\CustomerListResource;
use App\Models\Rent\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\CustomerRequest;
use App\Http\Resources\Rent\CustomerResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $customers = Customer::query()
            ->select(['id', 'name', 'is_blacklist', 'is_multi_contract'])
            ->when($data['search'] ?? null, function ($query, string $search) {
                // Escape LIKE wildcards typed by the user
                $term = addcslashes($search, '%_\\') . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 15);

        return CustomerListResource::collection($customers);
    }
    public function store(CustomerRequest $request)
    {
        $data     = $request->validated();
        $newPaths = [];

        try {
            $customer = DB::transaction(function () use ($request, $data, &$newPaths) {
                $customer = Customer::create(
                    Arr::except($data, ['details', ...Customer::DOCUMENT_TYPES])
                );

                $customer->details()->create($data['details'] ?? []);

                $this->saveImages($customer, $request, $newPaths);

                return $customer;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($newPaths); // don't leave orphan files
            throw $e;
        }

        return (new CustomerResource($customer->load(['details', 'images'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Customer $customer)
    {
        return new CustomerResource($customer->load(['details', 'images']));
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $data     = $request->validated();
        $newPaths = [];
        $oldPaths = [];

        try {
            DB::transaction(function () use ($request, $data, $customer, &$newPaths, &$oldPaths) {
                $customer->update(
                    Arr::except($data, ['details', ...Customer::DOCUMENT_TYPES])
                );

                if (isset($data['details'])) {
                    $customer->details()->updateOrCreate([], $data['details']);
                }

                $this->saveImages($customer, $request, $newPaths, $oldPaths);
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($newPaths);
            throw $e;
        }

        // Only remove replaced files once the transaction has committed
        Storage::disk('public')->delete($oldPaths);

        return new CustomerResource($customer->fresh(['details', 'images']));
    }

    public function destroy(Customer $customer)
    {
        // details: removed by the FK cascade
        // images (rows + files): removed by the deleting hook in the Customer model
        $customer->delete();

        Storage::disk('public')->deleteDirectory("customers/{$customer->id}");

        return response()->noContent();
    }

    /**
     * Store each uploaded document and create or replace its images row.
     * Collects the new paths (for rollback cleanup) and replaced paths (to delete after commit).
     */
    private function saveImages(Customer $customer, CustomerRequest $request, array &$newPaths, array &$oldPaths = []): void
    {
        foreach (Customer::DOCUMENT_TYPES as $type) {
            if (! $request->hasFile($type)) {
                continue;
            }

            $path       = $request->file($type)->store("customers/{$customer->id}", 'public');
            $newPaths[] = $path;

            $image = $customer->images()->where('type', $type)->first();

            if ($image) {
                $oldPaths[] = $image->path;
                $image->update(['path' => $path]);
            } else {
                $customer->images()->create(['path' => $path, 'type' => $type]);
            }
        }
    }
}
