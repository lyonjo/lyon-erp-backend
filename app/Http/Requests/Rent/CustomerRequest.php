<?php

namespace App\Http\Requests\Rent;

use App\Enums\ClientSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Create: required. Update: only validate what was sent.
        $req   = $this->isMethod('post') ? 'required' : 'sometimes';
        $image = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

        // Compare against the issue date only when it was actually sent
        $after = fn(string $issue) => filled($this->input($issue))
            ? ["after_or_equal:$issue"] : [];

        return [
            'name'              => [$req, 'string', 'max:255'],
            'email'             => ['nullable', 'email', 'max:255'],
            'client_side'       => [$req, Rule::enum(ClientSide::class)],
            'country_code'      => ['nullable', 'string', 'max:8'],
            'phone_number'      => ['nullable', 'string', 'max:32'],
            'address'           => ['nullable', 'string', 'max:1000'],
            'is_multi_contract' => ['sometimes', 'boolean'],
            'is_blacklist'      => ['sometimes', 'boolean'],
            'user_id'           => ['nullable', 'exists:users,id'],

            'details'                         => ['sometimes', 'array'],
            'details.nationality'             => ['nullable', 'string', 'max:100'],
            'details.place_of_birth'          => ['nullable', 'string', 'max:100'],
            'details.date_of_birth'           => ['nullable', 'date', 'before:today'],
            'details.passport_place_of_issue' => ['nullable', 'string', 'max:100'],
            'details.passport_date_of_issue'  => ['nullable', 'date'],
            'details.passport_date_of_expire' => ['nullable', 'date', ...$after('details.passport_date_of_issue')],
            'details.license_no'              => ['nullable', 'string', 'max:50'],
            'details.license_place_of_issue'  => ['nullable', 'string', 'max:100'],
            'details.license_date_of_issue'   => ['nullable', 'date'],
            'details.license_date_of_expire'  => ['nullable', 'date', ...$after('details.license_date_of_issue')],

            'id_front'      => $image,
            'id_back'       => $image,
            'license_front' => $image,
            'license_back'  => $image,
            'passport'      => $image,
        ];
    }
}
