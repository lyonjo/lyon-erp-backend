<?php

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;

class CustomerCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Create: required. Update: only validate what was sent.
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name'         => [$req, 'string', 'max:255'],
            'email'        => [$req, 'email', 'max:255'],
            'country_code' => [$req, 'string', 'max:10'],
            'phone'        => [$req, 'string', 'max:30'],
            'address'      => [$req, 'string', 'max:255'],
            'is_blacklist'      => ['sometimes', 'boolean'],
            'is_multi_contract' => ['sometimes', 'boolean'],
            'user_id'      => ['nullable', 'exists:users,id'],
        ];
    }
}
