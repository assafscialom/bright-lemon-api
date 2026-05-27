<?php

namespace App\Services;

use App\Exceptions\EmsShipmentException;
use App\Models\Shipment;

/**
 * Computes a shipping quote for a shipment using the internal country-group
 * pricing tables (managed by superadmin) instead of calling Israel Post.
 *
 * Class name kept stable on purpose — AdminShipmentController and tests still
 * type-hint this name; the implementation is now backed by
 * ShippingPriceService and the country_groups / country_group_price_tiers /
 * country_group_countries tables.
 *
 * Markup% per drop location is honored when the shipment already has a
 * drop_location_id; otherwise the customer-facing price is returned with a
 * Shipper take equal to the raw shipper_price (no markup).
 */
class IsraelPostQuoteService
{
    public function __construct(private readonly ShippingPriceService $pricing)
    {
    }

    public function quoteForShipment(Shipment $shipment): Shipment
    {
        $weight = max((float) $shipment->weight_kg, 0.001);
        $country = (string) $shipment->destination_country;
        $dropLocationId = $shipment->drop_location_id ?? null;

        $quote = $this->pricing->quote($country, $weight, $dropLocationId);

        if (! $quote) {
            $message = "No pricing configured for destination '{$country}' at {$weight}kg. "
                .'Ask a superadmin to add the country to a Country Group and set a matching weight tier.';
            $this->markFailed($shipment, $message);
            throw new EmsShipmentException($message);
        }

        $shipment->update([
            // shipping_price is the customer-facing amount; that's what the
            // drop location collects and what shows on the confirmation page.
            'shipping_price' => $quote['customer_price'],
            'shipping_price_currency' => $quote['currency'],
            'shipping_quote_service' => 'Israel Post EMS',
            'shipping_quote_status' => 'quoted',
            'shipping_quote_error' => null,
            'shipping_quoted_at' => now(),
        ]);

        return $shipment->refresh();
    }

    private function markFailed(Shipment $shipment, string $message): void
    {
        $shipment->update([
            'shipping_quote_status' => 'failed',
            'shipping_quote_error' => $message,
        ]);
    }
}
