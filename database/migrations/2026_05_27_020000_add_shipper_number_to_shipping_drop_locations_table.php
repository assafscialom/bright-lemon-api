<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            // "מספר שיפר" — Shipper customer/account number for this branch
            // (per IL Post's customer system). Nullable: pre-existing rows
            // don't have one yet, and not every branch is a Shipper customer.
            $table->string('shipper_number', 40)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_drop_locations', function (Blueprint $table) {
            $table->dropColumn('shipper_number');
        });
    }
};
