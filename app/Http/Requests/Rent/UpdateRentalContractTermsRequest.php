<?php

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRentalContractTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Add your administrator permission policy here.
    }

    public function rules(): array
    {
        return [
            'paragraph_en' => ['present', 'nullable', 'string', 'max:50000'],
            'paragraph_ar' => ['present', 'nullable', 'string', 'max:50000'],
            'clauses' => ['present', 'array', 'max:100'],
            'clauses.*.text_en' => ['required', 'string', 'max:10000'],
            'clauses.*.text_ar' => ['required', 'string', 'max:10000'],
        ];
    }
}
