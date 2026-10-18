<?php

declare(strict_types=1);

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization belongs to the tenant auth + module middleware and any
        // fine-grained permission policy enabled by the application.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
        ];
    }
}
