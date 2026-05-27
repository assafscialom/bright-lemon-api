<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
