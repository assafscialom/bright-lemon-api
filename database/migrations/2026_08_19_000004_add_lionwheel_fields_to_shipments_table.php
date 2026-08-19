<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The courier task booked for a VIP collection.
     *
     * The task id is stored so the same shipment is never dispatched twice,
     * and the tracking link so the desk can answer "where is the driver"
     * without logging into LionWheel. The error is kept too: a VIP shipment
     * with no task is something someone has to act on, and a null task with
     * no explanation gives them nowhere to start.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('lionwheel_task_id')->nullable()->index()->after('vip_fee_amount');
            $table->string('lionwheel_public_id', 40)->nullable()->after('lionwheel_task_id');
            $table->string('lionwheel_tracking_link')->nullable()->after('lionwheel_public_id');
            $table->timestamp('lionwheel_dispatched_at')->nullable()->after('lionwheel_tracking_link');
            $table->text('lionwheel_error')->nullable()->after('lionwheel_dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'lionwheel_task_id',
                'lionwheel_public_id',
                'lionwheel_tracking_link',
                'lionwheel_dispatched_at',
                'lionwheel_error',
            ]);
        });
    }
};
