<?php

declare(strict_types=1);

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SoldCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Tax is intentionally controlled by the backend, never by user input.
        $this->merge(['tax' => false]);
    }

    public function rules(): array
    {
        $soldCar = $this->route('soldCar');
        $ignoreId = is_object($soldCar) ? $soldCar->getKey() : null;

        return [
            'vehicle_id' => [
                'required', 'integer', 'exists:vehicles,id',
                Rule::unique('sold_cars', 'vehicle_id')->ignore($ignoreId),
            ],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'buyer_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'tax' => ['boolean'],
        ];
    }
}
