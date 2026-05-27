<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CountryGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function countries(): HasMany
    {
        return $this->hasMany(CountryGroupCountry::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(CountryGroupPriceTier::class)->orderBy('max_weight_kg');
    }
}
