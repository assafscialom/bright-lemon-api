<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_group_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_group_id')
                ->constrained('country_groups')
                ->cascadeOnDelete();
            // ISO 3166-1 alpha-2 (e.g. "AU"). Keep the country name too so we
            // can show it in the admin without a separate lookup, and so
            // re-naming the source list later doesn't desync historical rows.
            $table->string('country_code', 2);
            $table->string('country_name', 120);
            $table->timestamps();

            // A country can only live in one group — otherwise pricing is
            // ambiguous for shipments to that destination.
            $table->unique('country_code');
            $table->index('country_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_group_countries');
    }
};
