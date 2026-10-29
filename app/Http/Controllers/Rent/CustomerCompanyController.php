<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\CustomerCompanyRequest;
use App\Http\Resources\Rent\CustomerCompanyResource;
use App\Models\Rent\CustomerCompany;
use Illuminate\Http\Request;
use App\Services\Rent\AdminLogger;

class CustomerCompanyController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'is_blacklist' => ['nullable', 'boolean'],
            'is_multi_contract' => ['nullable', 'boolean'],
        ]);

        $companies = CustomerCompany::query()
            ->when($data['search'] ?? null, function ($query, string $search) {
                $term = addcslashes($search, '%_\\') . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when(isset($data['is_blacklist']), fn($q) => $q->where('is_blacklist', $data['is_blacklist']))
            ->when(isset($data['is_multi_contract']), fn($q) => $q->where('is_multi_contract', $data['is_multi_contract']))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 15);

        return CustomerCompanyResource::collection($companies);
    }

    public function store(CustomerCompanyRequest $request)
    {
        $company = CustomerCompany::create($request->validated());
        AdminLogger::log(
            action: 'created',
            model: CustomerCompany::class,
            modelId: $company->id,
            description: "Created customer company #{$company->id}",
            data: $company->toArray()
        );
        return $this->companyResponse($company, 201);
    }

    public function show(CustomerCompany $customerCompany)
    {
        return $this->companyResponse($customerCompany);
    }

    public function update(CustomerCompanyRequest $request, CustomerCompany $customerCompany)
    {
        $customerCompany->update($request->validated());
        AdminLogger::log(
            action: 'updated',
            model: CustomerCompany::class,
            modelId: $customerCompany->id,
            description: "Updated customer company #{$customerCompany->id}",
            data: $customerCompany->toArray()
        );
        return $this->companyResponse($customerCompany->fresh());
    }

    /**
     * Preserve the existing API resource shape and include admin_name even when
     * the deployed CustomerCompanyResource has not yet been updated.
     */
    private function companyResponse(CustomerCompany $company, int $status = 200)
    {
        $response = (new CustomerCompanyResource($company))->response();
        $payload = $response->getData(true);

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload['data']['admin_name'] = $company->admin_name;
            $payload['data']['note'] = $company->note;
        } else {
            $payload['admin_name'] = $company->admin_name;
            $payload['note'] = $company->note;
        }

        return $response->setData($payload)->setStatusCode($status);
    }

    public function destroy(CustomerCompany $customerCompany)
    {
        $customerCompany->delete();

        return response()->noContent();
    }
}
