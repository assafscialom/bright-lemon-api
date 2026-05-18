<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('recipient_postal_code', 30)->nullable()->after('recipient_po_box');
            $table->string('ems_status', 30)->nullable()->after('postal_ref');
            $table->string('ems_tracking_number')->nullable()->after('ems_status');
            $table->string('ems_label_format', 20)->nullable()->after('ems_tracking_number');
            $table->string('ems_label_extension', 20)->nullable()->after('ems_label_format');
            $table->longText('ems_label_content')->nullable()->after('ems_label_extension');
            $table->text('ems_error')->nullable()->after('ems_label_content');
            $table->timestamp('ems_created_at')->nullable()->after('ems_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'recipient_postal_code',
                'ems_status',
                'ems_tracking_number',
                'ems_label_format',
                'ems_label_extension',
                'ems_label_content',
                'ems_error',
                'ems_created_at',
            ]);
        });
    }
};
