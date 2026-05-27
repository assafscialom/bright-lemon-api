<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShippingPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public-facing pricing lookup driven entirely by the internal country-group
 * tables (no IL Post round-trip). Used by the SendPackage form to surface the
 * shipping price to the customer at quote time, before any admin gets
 * involved.
 *
 * Returns only the customer-facing amount — the Shipper / drop-location
 * split is admin-only and lives behind the admin endpoints.
 */
class PublicShippingQuoteController extends Controller
{
    public function quote(Request $request, ShippingPriceService $pricing): JsonResponse
    {
        $data = $request->validate([
            // ISO-2 code (preferred) or full English name as it appears in the
            // country picker. The pricing service accepts both.
            'country' => ['required', 'string', 'max:120'],
            'weight_kg' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            // Optional — when the customer has picked a drop-off branch we
            // can already factor in its markup. The customer_price returned
            // is the same either way (drop-location markup only changes the
            // internal split), but accepting it now keeps the door open for
            // future per-branch promo pricing without breaking the contract.
            'drop_location_id' => ['nullable', 'integer'],
        ]);

        $quote = $pricing->quote(
            (string) $data['country'],
            (float) $data['weight_kg'],
            isset($data['drop_location_id']) ? (int) $data['drop_location_id'] : null,
        );

        if (! $quote) {
            return response()->json([
                'message' => 'No pricing configured for this destination at this weight.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'customer_price' => $quote['customer_price'],
                'currency' => $quote['currency'],
            ],
        ]);
    }
}
