<?php

declare(strict_types=1);

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;

class ExtraItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Access controlled by existing auth:sanctum and module:rent middleware.
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'is_daily' => ['required', 'boolean'],
        ];
    }
}
