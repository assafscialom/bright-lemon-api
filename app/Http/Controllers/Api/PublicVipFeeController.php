<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LionWheelPickupService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * The VIP collection fee, for the send form.
 *
 * Public because the form is: a customer decides between dropping the parcel
 * off and having it collected before anyone asks them to log in, and that
 * decision costs money they are entitled to see first.
 *
 * Read-only. Changing it is an admin action and lives behind /admin/settings.
 */
class PublicVipFeeController extends Controller
{
    public function show(LionWheelPickupService $courier): JsonResponse
    {
        return response()->json([
            'data' => [
                'vip_fee' => Settings::vipCollectionFee(),
                'currency' => 'ILS',
                'includes_vat' => true,
                // The destinations a courier can actually be booked for.
                // Served from the same map the dispatcher uses, so the form
                // and the booking can never disagree about what is possible —
                // and widening it later is a config change, not a release.
                'supported_destinations' => array_keys(
                    (array) config('brightlemon.lionwheel.destinations', [])
                ),
            ],
        ]);
    }
}
