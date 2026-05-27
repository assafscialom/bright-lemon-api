<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_group_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_group_id')
                ->constrained('country_groups')
                ->cascadeOnDelete();
            // "up to X kg" — the tier is selected when the shipment weight is
            // ≤ max_weight_kg. Tiers within a group must be a continuous step
            // function ordered by max_weight_kg ascending.
            $table->decimal('max_weight_kg', 8, 3);
            // The two prices that drive the marketplace split:
            //   customer_price → what the customer sees and what the drop
            //                    location collects from them.
            //   shipper_price  → Shipper's cost basis. The drop location's
            //                    markup% (see shipping_drop_locations) is
            //                    applied on top of this to get Shipper's
            //                    actual take per shipment.
            $table->decimal('customer_price', 10, 2);
            $table->decimal('shipper_price', 10, 2);
            $table->char('currency', 3)->default('ILS');
            $table->timestamps();

            $table->index(['country_group_id', 'max_weight_kg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_group_price_tiers');
    }
};
