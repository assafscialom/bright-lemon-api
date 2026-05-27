<?php

namespace Database\Seeders;

use App\Models\GoodsType;
use Illuminate\Database\Seeder;

class GoodsTypeSeeder extends Seeder
{
    public function run(): void
    {
        $initial = [
            'Clothes and shoes second hand for personal use only',
            'Shoe accessories',
            'Food, supplements',
            'Baby clothing',
            'Clothing',
        ];

        foreach ($initial as $index => $name) {
            // Idempotent — re-running the seeder won't duplicate rows. New
            // entries can be added later via the superadmin UI.
            GoodsType::firstOrCreate(
                ['name' => $name],
                [
                    'is_active' => true,
                    'sort_order' => $index * 10,
                ],
            );
        }
    }
}
