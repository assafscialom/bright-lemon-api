<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether this shipment was booked as a VIP collection, and what the fee
     * was at the time.
     *
     * The amount is stored alongside the flag rather than looked up from
     * settings when needed: the fee is admin-editable, and a shipment quoted
     * at one price must not silently re-price itself because someone changed
     * the setting a month later.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->boolean('is_vip')->default(false)->index()->after('drop_location_id');
            $table->decimal('vip_fee_amount', 10, 2)->nullable()->after('is_vip');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['is_vip', 'vip_fee_amount']);
        });
    }
};
