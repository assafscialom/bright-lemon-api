<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pricing model rework.
 *
 * Old model: each tier held customer_price + shipper_price (Shipper's base),
 * and the drop location's markup% was applied on top of shipper_price.
 *
 * New model: the admin sets ONLY the customer_price per tier. The drop
 * location's markup% is the Shipper's share OF THE CUSTOMER PRICE — Shipper
 * take = customer_price × markup%, and the branch keeps the rest. The tier's
 * second field is now the REAL postal (EMS) cost Shipper pays the carrier, used
 * only to compute Shipper's net (take − postal cost) in the reports.
 *
 * So: rename country_group_price_tiers.shipper_price → postal_cost, and freeze
 * the postal cost on each shipment alongside the existing split snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_group_price_tiers', function (Blueprint $table) {
            $table->renameColumn('shipper_price', 'postal_cost');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('postal_cost_amount', 10, 2)->nullable()->after('markup_percent_at_quote');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('postal_cost_amount');
        });

        Schema::table('country_group_price_tiers', function (Blueprint $table) {
            $table->renameColumn('postal_cost', 'shipper_price');
        });
    }
};
