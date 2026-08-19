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
            // The send form asks the customer to choose between dropping the
            // parcel off and having it collected, before any branch is picked.
            // That choice is what decides the fee for them.
            'is_vip' => ['nullable', 'boolean'],
        ]);

        $quote = $pricing->quote(
            (string) $data['country'],
            (float) $data['weight_kg'],
            isset($data['drop_location_id']) ? (int) $data['drop_location_id'] : null,
            $request->boolean('is_vip'),
        );

        if (! $quote) {
            return response()->json([
                'message' => 'No pricing configured for this destination at this weight.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'customer_price' => $quote['customer_price'],
                // Broken out so the form can show the customer what the
                // collection added rather than a single number that does not
                // match the price table they were just looking at.
                'shipping_price' => $quote['shipping_price'],
                'vip_fee' => $quote['vip_fee'],
                'is_vip' => $quote['is_vip'],
                'currency' => $quote['currency'],
            ],
        ]);
    }
}
