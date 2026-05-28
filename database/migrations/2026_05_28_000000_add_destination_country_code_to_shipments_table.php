<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // ISO 3166-1 alpha-2 code of the destination, linking the shipment
            // to the country registry (country_group_countries.country_code,
            // which is unique and carries the pricing group). We keep the
            // existing free-text destination_country for the label/display and
            // historical accuracy; this column is the normalized connection.
            //
            // No hard FK constraint on purpose: a country row can be removed
            // when a superadmin reorganizes groups, and we don't want that to
            // null out the code recorded on a historical shipment. The Eloquent
            // relationship provides the connection; the code itself is stable.
            $table->string('destination_country_code', 2)
                ->nullable()
                ->after('destination_country');
            $table->index('destination_country_code');
        });

        // Backfill existing shipments: match the stored destination string
        // (either an ISO-2 code or the full country name) against the country
        // registry and copy its canonical code over.
        $countries = DB::table('country_group_countries')
            ->select('country_code', 'country_name')
            ->get();

        foreach ($countries as $country) {
            // Match on the full name (case-insensitive) ...
            DB::table('shipments')
                ->whereNull('destination_country_code')
                ->whereRaw('LOWER(destination_country) = ?', [strtolower($country->country_name)])
                ->update(['destination_country_code' => $country->country_code]);

            // ... or where the stored value is already the ISO code.
            DB::table('shipments')
                ->whereNull('destination_country_code')
                ->whereRaw('UPPER(destination_country) = ?', [strtoupper($country->country_code)])
                ->update(['destination_country_code' => $country->country_code]);
        }
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['destination_country_code']);
            $table->dropColumn('destination_country_code');
        });
    }
};
