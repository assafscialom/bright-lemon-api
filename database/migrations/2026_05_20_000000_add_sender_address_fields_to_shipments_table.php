<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('sender_email')->nullable()->after('sender_phone_normalized');
            $table->string('sender_city')->nullable()->after('sender_email');
            $table->string('sender_street')->nullable()->after('sender_city');
            $table->string('sender_number', 30)->nullable()->after('sender_street');
            $table->string('sender_postal_code', 30)->nullable()->after('sender_number');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'sender_email',
                'sender_city',
                'sender_street',
                'sender_number',
                'sender_postal_code',
            ]);
        });
    }
};
