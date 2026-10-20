<?php

declare(strict_types=1);

namespace App\Http\Requests\Rent;

use App\Enums\MaintenanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Add your granular maintenance write permission check here when enabled.
        return true;
    }

    public function rules(): array
    {
        return [
            'car_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'service_key' => ['required', Rule::enum(MaintenanceService::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'details' => ['nullable', 'string', 'max:10000'],
            'counter_km' => ['required', 'integer', 'min:0'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
