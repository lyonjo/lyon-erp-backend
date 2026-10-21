<?php

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'client_type' => ['required', Rule::in(['customer', 'company'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id', 'required_if:client_type,customer'],
            'customer_company_id' => ['nullable', 'integer', 'exists:customer_companies,id', 'required_if:client_type,company'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'pick_up_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'return_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'car_id' => ['required', 'integer', 'exists:vehicles,id'],
            'extra_item_ids' => ['sometimes', 'array', 'max:100'],
            'extra_item_ids.*' => ['integer', 'distinct', 'exists:extra_items,id'],
            'is_taxable' => ['required', 'boolean'],
            'payment_method' => ['required', Rule::in(['cash', 'online'])],
        ];
    }
}
