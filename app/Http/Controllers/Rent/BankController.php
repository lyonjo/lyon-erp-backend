<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\BankRequest;
use App\Models\Rent\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = Bank::query()->with('company:id,name');
        $search = trim($input['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('company', function ($company) use ($search) {
                        $company->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $banks = $query->orderByDesc('id')->paginate((int) ($input['per_page'] ?? 15));

        return response()->json([
            'data' => $banks->getCollection()->map(fn(Bank $bank) => $this->row($bank))->values(),
            'meta' => [
                'current_page' => $banks->currentPage(),
                'last_page' => $banks->lastPage(),
                'per_page' => $banks->perPage(),
                'total' => $banks->total(),
            ],
        ]);
    }

    public function show(Bank $bank): JsonResponse
    {
        return response()->json(['data' => $this->row($bank->load('company:id,name'))]);
    }

    public function store(BankRequest $request): JsonResponse
    {
        $bank = DB::transaction(fn() => Bank::create([
            ...$request->validated(),
            'account_id' => null,
        ]));

        return response()->json(['data' => $this->row($bank->load('company:id,name'))], 201);
    }

    public function update(BankRequest $request, Bank $bank): JsonResponse
    {
        // Intentionally preserve existing account_id; management is deferred.
        $bank->update($request->validated());

        return response()->json(['data' => $this->row($bank->load('company:id,name'))]);
    }

    public function destroy(Bank $bank): JsonResponse
    {
        $bank->delete();
        return response()->json(['message' => 'Bank deleted successfully.']);
    }

    private function row(Bank $bank): array
    {
        return [
            'id' => $bank->id,
            'name' => $bank->name,
            'company_id' => $bank->company_id,
            'company_name' => $bank->company?->name,
            'account_id' => $bank->account_id,
            'for_sold_car' => $bank->for_sold_car,
            'for_lawsuits' => $bank->for_lawsuits,
            'for_companies' => $bank->for_companies,
            'for_booking' => $bank->for_booking,
            'for_contracts' => $bank->for_contracts,
        ];
    }
}
