<?php
namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'supplier_name' => ['required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:12'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'representative_country_code' => ['nullable', 'string', 'max:12'],
            'representative_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:5000'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')],
        ];
    }
}
