<?php

declare(strict_types=1);

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Rent\VehicleModel;
use App\Models\Rent\Supplier;

class ExternalVehicleRequest extends FormRequest
{
    public function authorize(): bool { return true; } // Add your policy/permission middleware on the routes.

    protected function prepareForValidation(): void
    {
        $nullable = [
            'vin_number', 'engine_number', 'registration_number', 'engine_size',
            'license_expiration', 'insurance_expiration', 'odometer',
        ];
        $values = [];
        foreach ($nullable as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $values[$key] = null;
            }
        }
        if ($values) $this->merge($values);
    }

    public function rules(): array
    {
        $currentId = $this->route('externalVehicle')?->getKey();
        return [
            'number' => ['required', 'string', 'max:100', Rule::unique('external_vehicles', 'number')->ignore($currentId)],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'model_id' => ['required', 'integer', 'exists:models,id', function ($attribute, $value, $fail) {
                if ($this->filled('brand_id') && !VehicleModel::query()->whereKey($value)->where('brand_id', $this->integer('brand_id'))->exists()) {
                    $fail('The selected model does not belong to the selected brand.');
                }
            }],
            'model_year' => ['required', 'integer', 'between:1950,2100'],
            'supplier_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
                function ($attribute, $value, $fail) {
                    if (!Supplier::query()
                        ->whereKey($value)
                        ->whereHas('services', function ($serviceQuery) {
                            $serviceQuery->whereRaw('LOWER(TRIM(name)) IN (?, ?)', [
                                'rent car',
                                'تأجير السيارات',
                            ]);
                        })->exists()) {
                        $fail('The selected supplier must provide the rent car service (rent car / تأجير السيارات).');
                    }
                },
            ],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'vin_number' => ['nullable', 'string', 'max:100'],
            'engine_number' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'fuel_type' => ['required', Rule::in(['gasoline', 'diesel', 'electric', 'hyprid'])],
            'engine_size' => ['nullable', 'string', 'max:100'],
            'passenger_number' => ['required', 'integer', 'between:1,100'],
            'gearbox_type' => ['required', Rule::in(['manual', 'automatic'])],
            'camera' => ['required', 'boolean'],
            'bluetooth' => ['required', 'boolean'],
            'license_expiration' => ['nullable', 'date'],
            'insurance_expiration' => ['nullable', 'date'],
            'current_km' => ['required', 'integer', 'min:0'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'boolean'],
        ];
    }
}
