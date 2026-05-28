<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CountryGroup;
use Illuminate\Http\JsonResponse;

/**
 * Public list of destination countries the customer can ship to, driven by the
 * superadmin's country groups. Each country carries its group's customer-facing
 * weight tiers so the SendPackage form can:
 *
 *   1. Populate the "Destination Country" select from the configured countries.
 *   2. Show the price-per-weight popup straight from the selected country's
 *      group tiers (customer_price only — the Shipper/branch split is admin-only).
 */
class PublicShippingDestinationController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = CountryGroup::query()
            ->where('is_active', true)
            ->with(['countries', 'priceTiers'])
            ->orderBy('sort_order')
            ->get();

        $destinations = [];

        foreach ($groups as $group) {
            $tiers = $group->priceTiers
                ->map(fn ($tier) => [
                    'max_weight_kg' => (float) $tier->max_weight_kg,
                    'customer_price' => (float) $tier->customer_price,
                    'currency' => $tier->currency,
                ])
                ->values()
                ->all();

            foreach ($group->countries as $country) {
                $destinations[] = [
                    'country_code' => $country->country_code,
                    'country_name' => $country->country_name,
                    'country_group_id' => $group->id,
                    'currency' => $tiers[0]['currency'] ?? 'ILS',
                    'tiers' => $tiers,
                ];
            }
        }

        usort($destinations, fn ($a, $b) => strcmp($a['country_name'], $b['country_name']));

        return response()->json(['data' => $destinations]);
    }
}
