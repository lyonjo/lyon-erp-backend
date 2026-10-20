<?php

declare(strict_types=1);

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('driver')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:100', Rule::unique('drivers', 'license_number')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'boolean'],
        ];
    }
}
