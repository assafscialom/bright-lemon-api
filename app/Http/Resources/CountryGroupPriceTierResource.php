<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryGroupPriceTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_group_id' => $this->country_group_id,
            'max_weight_kg' => (float) $this->max_weight_kg,
            'customer_price' => (float) $this->customer_price,
            'shipper_price' => (float) $this->shipper_price,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
