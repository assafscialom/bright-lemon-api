<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The handful of global values an admin can change without a deploy.
 *
 * Only known keys are writable — the endpoint takes a named field, not an
 * arbitrary key/value pair, so a typo cannot quietly create a second setting
 * that nothing reads.
 */
class AdminSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vip_collection_fee' => Settings::vipCollectionFee(),
                'currency' => 'ILS',
                'vip_fee_includes_vat' => true,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Capped rather than unbounded: this number is added to every VIP
            // customer's price, and a slipped decimal point is the kind of
            // mistake that only surfaces at the till.
            'vip_collection_fee' => ['required', 'numeric', 'min:0', 'max:10000'],
        ]);

        Settings::set(Settings::VIP_COLLECTION_FEE, round((float) $data['vip_collection_fee'], 2));

        return $this->index();
    }
}
