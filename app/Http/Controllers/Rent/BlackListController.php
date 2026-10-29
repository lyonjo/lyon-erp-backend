<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\Customer;
use App\Models\Rent\CustomerCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlackListController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:all,customer,company'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = $data['type'] ?? 'all';
        $search = $data['search'] ?? null;
        $perPage = $data['per_page'] ?? 15;

        $customers = Customer::query()
            ->select([
                'id',
                'name',
                'email',
                'country_code',
                DB::raw('phone_number as phone'),
                'note',
                'is_blacklist',
                DB::raw("'customer' as type"),
                DB::raw('created_at'),
            ])
            ->where('is_blacklist', true);

        $companies = CustomerCompany::query()
            ->select([
                'id',
                'name',
                'email',
                'country_code',
                'phone',
                'note',
                'is_blacklist',
                DB::raw("'company' as type"),
                DB::raw('created_at'),
            ])
            ->where('is_blacklist', true);

        if (filled($search)) {
            $term = addcslashes($search, '%_\\') . '%';

            $customers->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone_number', 'like', $term);
            });

            $companies->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if ($type === 'customer') {
            $query = $customers->toBase();
        } elseif ($type === 'company') {
            $query = $companies->toBase();
        } else {
            $query = DB::query()->fromSub(
                $customers->toBase()->unionAll(
                    $companies->toBase()
                ),
                'blacklist'
            );
        }

        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->orderBy('type')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }
}
