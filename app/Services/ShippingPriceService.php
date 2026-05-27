<?php

namespace App\Services;

use App\Models\CountryGroup;
use App\Models\CountryGroupCountry;
use App\Models\CountryGroupPriceTier;
use App\Models\ShippingDropLocation;

/**
 * Internal pricing for a new shipment.
 *
 * Replaces the old IsraelPostQuoteService that hit Israel Post for a real-time
 * quote. The new model is fully internal:
 *
 *   1. Each destination country lives in exactly one CountryGroup.
 *   2. Each group has weight-tier rows: max_weight_kg, customer_price, shipper_price.
 *   3. The tier picked is the smallest tier whose max_weight_kg ≥ shipment weight.
 *   4. Each drop location has a markup_percent applied on top of shipper_price
 *      to compute Shipper's actual take per shipment.
 *
 * The drop location keeps the difference between what the customer paid and
 * what Shipper takes — that's its revenue.
 */
class ShippingPriceService
{
    /**
     * Compute the price breakdown for a (country, weight, drop_location).
     *
     * @return array{
     *     customer_price: float,
     *     shipper_price: float,
     *     shipper_take: float,
     *     drop_location_take: float,
     *     markup_percent: float,
     *     currency: string,
     *     tier_id: int,
     *     country_group_id: int,
     *     drop_location_id: ?int,
     * }|null  Returns null if the country isn't mapped to a group or no tier
     *         can hold the given weight.
     */
    public function quote(string $countryNameOrCode, float $weightKg, ?int $dropLocationId): ?array
    {
        $group = $this->resolveGroup($countryNameOrCode);
        if (! $group) {
            return null;
        }

        $tier = $this->resolveTier($group, $weightKg);
        if (! $tier) {
            return null;
        }

        $markupPercent = $this->resolveMarkupPercent($dropLocationId);

        // Shipper's take = shipper_price + (shipper_price * markup%).
        // The customer always pays customer_price; the drop location keeps the
        // difference between customer_price and shipper_take.
        $shipperTake = (float) $tier->shipper_price * (1 + $markupPercent / 100);
        $shipperTake = round($shipperTake, 2);

        $dropLocationTake = round((float) $tier->customer_price - $shipperTake, 2);

        return [
            'customer_price' => (float) $tier->customer_price,
            'shipper_price' => (float) $tier->shipper_price,
            'shipper_take' => $shipperTake,
            'drop_location_take' => $dropLocationTake,
            'markup_percent' => $markupPercent,
            'currency' => $tier->currency,
            'tier_id' => $tier->id,
            'country_group_id' => $group->id,
            'drop_location_id' => $dropLocationId,
        ];
    }

    /**
     * Find the CountryGroup for a destination. Accepts either the ISO-2 code
     * (e.g. "AU") or the full country name as the customer typed it on the
     * form (e.g. "Australia"). Case-insensitive matching on both axes.
     */
    private function resolveGroup(string $countryNameOrCode): ?CountryGroup
    {
        $normalized = strtoupper(trim($countryNameOrCode));

        // ISO-2 code path — fast and unambiguous.
        if (strlen($normalized) === 2) {
            $row = CountryGroupCountry::where('country_code', $normalized)->first();
            if ($row) {
                return CountryGroup::where('id', $row->country_group_id)
                    ->where('is_active', true)
                    ->first();
            }
        }

        // Name path — match against the stored country_name (case-insensitive).
        $row = CountryGroupCountry::whereRaw('LOWER(country_name) = ?', [strtolower(trim($countryNameOrCode))])->first();
        if (! $row) {
            return null;
        }

        return CountryGroup::where('id', $row->country_group_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Smallest tier (by max_weight_kg) that still covers the given weight.
     * Returns null if the shipment exceeds every tier.
     */
    private function resolveTier(CountryGroup $group, float $weightKg): ?CountryGroupPriceTier
    {
        return $group->priceTiers()
            ->where('max_weight_kg', '>=', $weightKg)
            ->orderBy('max_weight_kg')
            ->first();
    }

    private function resolveMarkupPercent(?int $dropLocationId): float
    {
        if (! $dropLocationId) {
            return 0.0;
        }
        $loc = ShippingDropLocation::find($dropLocationId);
        return $loc ? (float) $loc->markup_percent : 0.0;
    }
}
