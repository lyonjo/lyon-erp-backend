<?php

namespace App\Http\Controllers\Rent;

use App\Http\Resources\CustomerListResource;
use App\Models\Rent\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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
}
