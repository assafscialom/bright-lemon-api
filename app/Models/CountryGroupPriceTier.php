<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryGroupPriceTier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'max_weight_kg' => 'float',
            'customer_price' => 'float',
            'shipper_price' => 'float',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CountryGroup::class, 'country_group_id');
    }
}
