<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('package_number', 12)->unique();
            $table->string('status', 40)->default('Registered')->index();
            $table->string('branch_name')->nullable();

            $table->string('sender_first_name');
            $table->string('sender_middle_name')->nullable();
            $table->string('sender_last_name');
            $table->string('sender_country_code', 8);
            $table->string('sender_mobile', 30);
            $table->string('sender_phone_normalized', 50)->index();
            $table->string('sender_passport_number', 50);
            $table->date('sender_passport_expires_at');
            $table->string('passport_file_path')->nullable();
            $table->longText('signature_data_url')->nullable();

            $table->string('package_type', 80);
            $table->string('destination_country', 120);
            $table->string('weight_label', 40);
            $table->decimal('weight_kg', 8, 2);
            $table->decimal('declared_value', 10, 2);
            $table->decimal('shipping_price', 10, 2);

            $table->string('recipient_first_name');
            $table->string('recipient_middle_name')->nullable();
            $table->string('recipient_last_name');
            $table->string('recipient_state')->nullable();
            $table->string('recipient_city');
            $table->string('recipient_street');
            $table->string('recipient_number')->nullable();
            $table->string('recipient_po_box')->nullable();
            $table->string('recipient_country_code', 8);
            $table->string('recipient_mobile', 30);
            $table->string('recipient_phone_normalized', 50)->index();

            $table->string('payment_ref')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('postal_ref')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('label_printed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
