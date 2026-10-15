<?php

namespace App\Http\Resources\Rent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'tax_number' => $this->tax_number,
            'is_main' => (bool) $this->is_main,
            'logo_url' => $this->whenLoaded('logo', fn () => $this->logo?->url),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
