<?php

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }

    public function rules(): array
    {
        return [
            'booking_tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'car_rental_km_daily' => ['required', 'integer', 'min:0', 'max:1000000'],
            'car_rental_price_per_km' => ['required', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,4'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }
}
