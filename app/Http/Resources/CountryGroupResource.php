<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            // Eager-loaded by the controller; we always return both arrays so
            // the admin UI can render the whole group with one request.
            'countries' => $this->countries->map(fn ($c) => [
                'id' => $c->id,
                'country_code' => $c->country_code,
                'country_name' => $c->country_name,
            ])->values()->all(),
            'price_tiers' => CountryGroupPriceTierResource::collection($this->priceTiers),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
