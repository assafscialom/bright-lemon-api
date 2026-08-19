<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VIP branches: a courier collects the parcel from the sender instead of
     * the sender bringing it to a counter.
     *
     * Modelled as a flag on a drop location rather than a separate concept
     * because everything downstream — the pricing split, the shipment's
     * drop_location_id, the admin list — already keys off a location. A
     * parallel entity would have to be threaded through all of it to say the
     * same thing.
     *
     * A VIP branch has no counter, so address_line_1 and city stop being
     * required. They are made nullable here rather than merely optional in
     * the form: a NOT NULL column would reject the row no matter what the
     * validator allows.
     */
    public function up(): void
    {
        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            $table->boolean('is_vip')->default(false)->index()->after('is_active');
        });

        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            $table->string('address_line_1')->nullable()->change();
            $table->string('city', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            $table->dropColumn('is_vip');
        });
        // address_line_1 / city are deliberately left nullable: rows created
        // as VIP have no value to put back, and re-imposing NOT NULL would
        // fail on them.
    }
};
