<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Books the courier for a VIP shipment, exactly once.
 *
 * Separate from LionWheelPickupService so the "should we, and did we already"
 * decision is not tangled with the HTTP call — the guard is the part that
 * matters, since a second call means a second driver for the same parcel.
 */
class VipCollectionDispatcher
{
    public function __construct(
        private readonly LionWheelPickupService $courier = new LionWheelPickupService(),
    ) {
    }

    /**
     * @return bool whether a task exists for this shipment after the call
     */
    public function dispatch(Shipment $shipment): bool
    {
        if (! $shipment->is_vip) {
            return false;
        }

        // Already booked. Not an error, and deliberately not re-sent: the
        // task id being present is the only guard against a duplicate driver.
        if ($shipment->lionwheel_task_id) {
            return true;
        }

        try {
            $result = $this->courier->createTask($shipment);
        } catch (Throwable $e) {
            // The shipment stands whatever happens here. The customer was told
            // a representative will call, and that remains true.
            Log::error('VIP dispatch threw', ['shipment' => $shipment->id, 'error' => $e->getMessage()]);
            $shipment->forceFill(['lionwheel_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            return false;
        }

        if (! ($result['ok'] ?? false)) {
            $shipment->forceFill([
                'lionwheel_error' => mb_substr((string) ($result['error'] ?? 'unknown'), 0, 500),
            ])->save();

            return false;
        }

        $shipment->forceFill([
            'lionwheel_task_id' => $result['task_id'] ?? null,
            'lionwheel_public_id' => $result['public_id'] ?? null,
            'lionwheel_tracking_link' => $result['tracking_link'] ?? null,
            'lionwheel_dispatched_at' => now(),
            'lionwheel_error' => null,
        ])->save();

        return true;
    }
}
