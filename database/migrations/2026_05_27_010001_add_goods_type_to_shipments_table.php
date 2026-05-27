<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Nullable on purpose — existing shipments don't have a goods_type
            // and we don't want to backfill them retroactively. The select on
            // the public form makes it required for new submissions.
            // Stored as plain string (the chosen label at submit time) rather
            // than a foreign key so renaming a goods type in admin doesn't
            // rewrite history on shipments already in motion.
            $table->string('goods_type', 200)->nullable()->after('package_type');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('goods_type');
        });
    }
};
