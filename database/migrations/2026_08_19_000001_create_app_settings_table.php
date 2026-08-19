<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-editable values that are neither per-branch nor per-country-group.
     *
     * The VIP collection fee is the first of these: one number, set once, that
     * applies wherever a VIP branch is chosen. Putting it in a migration or a
     * config file would mean a deploy every time the price moves, which is not
     * how a price behaves.
     *
     * Values are stored as text and read through App\Support\Settings, which
     * casts them. A typed column per setting would need a migration for every
     * new one.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // The fee agreed at launch: 130 ILS including VAT.
        DB::table('app_settings')->insert([
            'key' => 'vip_collection_fee',
            'value' => '130',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
