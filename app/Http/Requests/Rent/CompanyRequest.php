<?php

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add granular permissions when that project phase begins.
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post') && ! $this->route('company');
        // POST with _method=PUT is interpreted by Laravel as PUT.
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'location' => [$required, 'string', 'max:255'],
            'tax_number' => [$required, 'string', 'max:100'],
            'is_main' => ['sometimes', 'boolean'],
            // SVG is intentionally excluded: untrusted SVG can contain active content.
            'logo' => [Rule::requiredIf($creating), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
