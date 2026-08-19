<?php

namespace App\Services;

use App\Models\CountryGroup;
use App\Models\CountryGroupCountry;
use App\Models\CountryGroupPriceTier;
use App\Models\ShippingDropLocation;
use App\Support\Settings;

/**
 * Internal pricing for a new shipment.
 *
 * Replaces the old IsraelPostQuoteService that hit Israel Post for a real-time
 * quote. The new model is fully internal:
 *
 *   1. Each destination country lives in exactly one CountryGroup.
 *   2. Each group has weight-tier rows: max_weight_kg, customer_price, postal_cost.
 *   3. The tier picked is the smallest tier whose max_weight_kg ≥ shipment weight.
 *   4. The customer always pays customer_price. Each drop location has a
 *      markup_percent which is SHIPPER'S SHARE OF THE CUSTOMER PRICE:
 *          shipper_take       = customer_price × markup% / 100
 *          drop_location_take = customer_price − shipper_take   (the branch keeps the rest)
 *   5. postal_cost is the real EMS cost Shipper pays the carrier. It does not
 *      change the customer/branch split — it only yields Shipper's net:
 *          shipper_net = shipper_take − postal_cost
 *
 *   6. A VIP location has no counter — a courier collects from the sender —
 *      and adds a flat collection fee to what the customer pays. The fee is
 *      added to customer_price BEFORE the split, so the branch shares in it
 *      exactly as it shares in the shipping price. It is not Shipper-only
 *      revenue.
 *
 * Example: customer 130, markup 30% → shipper_take 39, branch keeps 91.
 *          A 20% branch → shipper_take 26, branch keeps 104 (branch earns more).
 *          The same 20% branch as VIP with a 130 fee → customer pays 260,
 *          shipper_take 52, branch keeps 208.
 */
class ShippingPriceService
{
    /**
     * Compute the price breakdown for a (country, weight, drop_location).
     *
     * @return array{
     *     customer_price: float,
     *     postal_cost: float,
     *     shipper_take: float,
     *     shipper_net: float,
     *     drop_location_take: float,
     *     markup_percent: float,
     *     currency: string,
     *     tier_id: int,
     *     country_group_id: int,
     *     drop_location_id: ?int,
     * }|null  Returns null if the country isn't mapped to a group or no tier
     *         can hold the given weight.
     */
    public function quote(
        string $countryNameOrCode,
        float $weightKg,
        ?int $dropLocationId,
        bool $vipRequested = false
    ): ?array
    {
        $group = $this->resolveGroup($countryNameOrCode);
        if (! $group) {
            return null;
        }

        $tier = $this->resolveTier($group, $weightKg);
        if (! $tier) {
            return null;
        }

        $location = $dropLocationId ? ShippingDropLocation::find($dropLocationId) : null;
        $markupPercent = $location ? (float) $location->markup_percent : 0.0;

        // A VIP collection is an addition to the shipping price, not a
        // replacement for it: the parcel still travels the same way, someone
        // just fetches it first.
        //
        // Two ways to arrive here. The admin can mark a location as VIP, and
        // the customer can pick VIP in the send form before any location
        // exists — which is the normal case, since that form never asks the
        // customer to choose a branch. Either is enough.
        $isVip = $vipRequested || (bool) ($location?->is_vip);
        $vipFee = $isVip ? Settings::vipCollectionFee() : 0.0;

        $shippingPrice = (float) $tier->customer_price;
        $customerPrice = round($shippingPrice + $vipFee, 2);
        $postalCost = (float) $tier->postal_cost;

        // The customer always pays customer_price. Shipper's take is its share
        // of that price (markup%); the drop location keeps the rest. Shipper's
        // net is its take minus the real postal cost it pays the carrier.
        $shipperTake = round($customerPrice * $markupPercent / 100, 2);
        $dropLocationTake = round($customerPrice - $shipperTake, 2);
        $shipperNet = round($shipperTake - $postalCost, 2);

        return [
            'customer_price' => $customerPrice,
            // What the shipping itself costs, and what the VIP collection
            // added. Kept apart so a receipt can name the fee rather than
            // presenting a single number the customer cannot reconcile.
            'shipping_price' => $shippingPrice,
            'vip_fee' => $vipFee,
            'is_vip' => $isVip,
            'postal_cost' => $postalCost,
            'shipper_take' => $shipperTake,
            'shipper_net' => $shipperNet,
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

}
