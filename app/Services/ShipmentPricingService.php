<?php

namespace App\Services;

class ShipmentPricingService
{
    private const WEIGHT_TABLE = [
        '0 – 0.5 kg' => ['kg' => 0.5, 'price' => 15],
        '0.5 – 1 kg' => ['kg' => 1, 'price' => 22],
        '1 – 2 kg' => ['kg' => 2, 'price' => 30],
        '2 – 5 kg' => ['kg' => 5, 'price' => 45],
        '5 – 10 kg' => ['kg' => 10, 'price' => 70],
        '10 – 15 kg' => ['kg' => 15, 'price' => 95],
        '15 – 20 kg' => ['kg' => 20, 'price' => 120],
        '20 – 30 kg' => ['kg' => 30, 'price' => 160],
    ];

    public function priceForWeightLabel(string $weightLabel): float
    {
        return (float) (self::WEIGHT_TABLE[$weightLabel]['price'] ?? 0);
    }

    public function weightKgForLabel(string $weightLabel): float
    {
        if (isset(self::WEIGHT_TABLE[$weightLabel])) {
            return (float) self::WEIGHT_TABLE[$weightLabel]['kg'];
        }

        // Generic fallback for labels generated from the country-group tiers
        // (e.g. "2 – 3 kg", "Up to 0.5 kg"). The bracket's upper bound is the
        // last number in the label, and equals a tier's max_weight_kg — which
        // is what the pricing service resolves the tier by.
        if (preg_match_all('/\d+(?:\.\d+)?/', $weightLabel, $matches) && ! empty($matches[0])) {
            return (float) end($matches[0]);
        }

        return 0.0;
    }
}
