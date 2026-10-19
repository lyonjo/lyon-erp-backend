<?php

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Rent\VehicleModel;

class VehicleSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    } // Route-level authorization must restrict vehicle management.
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:100'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'model_id' => ['required', 'integer', 'exists:models,id', function ($attr, $value, $fail) {
                if ($this->filled('brand_id') && !VehicleModel::whereKey($value)->where('brand_id', $this->integer('brand_id'))->exists()) $fail('The selected model does not belong to the selected brand.');
            }],
            'model_year' => ['required', 'integer', 'between:1950,2100'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'vin_number' => ['nullable', 'string', 'max:100'],
            'engine_number' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'fuel_type' => ['required', Rule::in(['gasoline', 'diesel', 'electric', 'hyprid'])],
            'passengers_number' => ['required', 'integer', 'between:1,100'],
            'gearbox_type' => ['required', Rule::in(['manual', 'automatic'])],
            'has_blutooth' => ['required', 'boolean'],
            'has_camera' => ['required', 'boolean'],
            'license_expiration' => ['nullable', 'date'],
            'insurance_expiration' => ['nullable', 'date'],
            'current_km' => ['required', 'integer', 'min:0'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['integer', 'distinct', 'exists:extra_features,id'],
            'main_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'gallery' => ['sometimes', 'array', 'max:12'],
            'gallery.*' => ['image', 'mimes:jpeg,png,webp', 'max:4096'],
            'delete_image_ids' => ['sometimes', 'array'],
            'delete_image_ids.*' => ['integer', 'distinct'],
        ];
    }
}
