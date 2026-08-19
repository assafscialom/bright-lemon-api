<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vip_fee' => Settings::vipCollectionFee(),
                'currency' => 'ILS',
                'includes_vat' => true,
            ],
        ]);
    }
}
