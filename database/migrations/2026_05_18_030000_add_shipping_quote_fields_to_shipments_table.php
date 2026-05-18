<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('shipping_price_currency', 3)->nullable()->after('shipping_price');
            $table->string('shipping_quote_service', 80)->nullable()->after('shipping_price_currency');
            $table->string('shipping_quote_status', 30)->nullable()->after('shipping_quote_service');
            $table->text('shipping_quote_error')->nullable()->after('shipping_quote_status');
            $table->timestamp('shipping_quoted_at')->nullable()->after('shipping_quote_error');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_price_currency',
                'shipping_quote_service',
                'shipping_quote_status',
                'shipping_quote_error',
                'shipping_quoted_at',
            ]);
        });
    }
};
