<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingDropLocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_vip' => 'boolean',
            'sort_order' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
