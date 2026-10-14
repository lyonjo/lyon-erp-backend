<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'nationality'       => $this->details?->nationality,
            'is_blacklist'      => $this->is_blacklist,
            'is_multi_contract' => $this->is_multi_contract,
        ];
    }
}
