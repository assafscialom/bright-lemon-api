<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // The drop-off branch that accepted (or will accept) this shipment.
            // Set on recordPayment so we can compute the Shipper/branch revenue
            // split with the markup_percent that was in effect at that moment.
            // Nullable until the customer / admin assigns one.
            $table->foreignId('drop_location_id')
                ->nullable()
                ->after('shipping_quoted_at')
                ->constrained('shipping_drop_locations')
                ->nullOnDelete();

            // Frozen snapshot of the pricing split so we don't have to recompute
            // (and risk drift) when tiers or markup% change later.
            $table->decimal('shipper_take_amount', 10, 2)->nullable()->after('drop_location_id');
            $table->decimal('drop_location_take_amount', 10, 2)->nullable()->after('shipper_take_amount');
            $table->decimal('markup_percent_at_quote', 6, 2)->nullable()->after('drop_location_take_amount');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drop_location_id');
            $table->dropColumn([
                'shipper_take_amount',
                'drop_location_take_amount',
                'markup_percent_at_quote',
            ]);
        });
    }
};
