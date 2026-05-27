<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            // Lat / lng are nullable on purpose — locations that pre-date this
            // migration won't have coordinates and we don't want to block them
            // until the admin fills them in. The public "branches on map" view
            // skips rows where either is null.
            $table->decimal('latitude', 10, 7)->nullable()->after('address_line_2');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
