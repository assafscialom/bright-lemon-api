<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            // Per-branch markup applied on top of shipper_price from the
            // country-group price tier. 0 = no markup (Shipper's take == raw
            // shipper_price). Allowed range 0..1000% to leave room for
            // promotional or premium branches; admin form clamps to 0..100
            // by default. Stored as percentage points (e.g. 30 == 30%).
            $table->decimal('markup_percent', 6, 2)->default(0)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            $table->dropColumn('markup_percent');
        });
    }
};
