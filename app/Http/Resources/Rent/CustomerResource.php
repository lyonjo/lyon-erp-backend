<?php

namespace App\Http\Resources\Rent;

use App\Models\Rent\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->user_id,
            'name'              => $this->name,
            'email'             => $this->email,
            'client_side'       => $this->client_side->value,
            'client_side_label' => $this->client_side->label(),
            'country_code'      => $this->country_code,
            'phone_number'      => $this->phone_number,
            'address'           => $this->address,
            'is_multi_contract' => $this->is_multi_contract,
            'is_blacklist'      => $this->is_blacklist,
            'note'              => $this->note,

            'details' => $this->whenLoaded(
                'details',
                fn() => $this->details?->makeHidden(['id', 'customer_id', 'created_at', 'updated_at'])
            ),

            // { id_front: url|null, id_back: url|null, ... }
            'images' => $this->whenLoaded('images', fn() => collect(Customer::DOCUMENT_TYPES)
                ->mapWithKeys(fn($type) => [
                    $type => $this->images->firstWhere('type', $type)?->url,
                ])),
        ];
    }
}
