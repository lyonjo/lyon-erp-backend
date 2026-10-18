<?php

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authentication and module middleware wrap these routes.
    }

    protected function prepareForValidation(): void
    {
        // account_id is deliberately not editable until an accounts endpoint exists.
        // Ignore any account_id supplied by a client.
        $this->request->remove('account_id');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'for_sold_car' => ['required', 'boolean'],
            'for_lawsuits' => ['required', 'boolean'],
            'for_companies' => ['required', 'boolean'],
            'for_booking' => ['required', 'boolean'],
            'for_contracts' => ['required', 'boolean'],
        ];
    }
}
