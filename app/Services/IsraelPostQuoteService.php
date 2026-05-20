<?php

namespace App\Services;

use App\Exceptions\EmsShipmentException;
use App\Models\Shipment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class IsraelPostQuoteService
{
    private const EMS_PRICING = [
        13 => ['half_kg' => 125.00, 'one_kg' => 133.00, 'increment' => 7.60],
        12 => ['half_kg' => 102.00, 'one_kg' => 106.00, 'increment' => 5.50],
        11 => ['half_kg' => 119.00, 'one_kg' => 123.00, 'increment' => 7.50],
        10 => ['half_kg' => 97.00, 'one_kg' => 106.00, 'increment' => 8.50],
        9 => ['half_kg' => 126.00, 'one_kg' => 137.00, 'increment' => 13.50],
        8 => ['half_kg' => 101.00, 'one_kg' => 113.00, 'increment' => 15.50],
        7 => ['half_kg' => 93.00, 'one_kg' => 109.00, 'increment' => 18.90],
        6 => ['half_kg' => 94.00, 'one_kg' => 110.00, 'increment' => 17.70],
        5 => ['half_kg' => 83.00, 'one_kg' => 93.00, 'increment' => 12.70],
        4 => ['half_kg' => 92.00, 'one_kg' => 103.00, 'increment' => 11.70],
        3 => ['half_kg' => 104.00, 'one_kg' => 110.00, 'increment' => 6.50],
        2 => ['half_kg' => 103.00, 'one_kg' => 129.00, 'increment' => 33.00],
        1 => ['half_kg' => 103.00, 'one_kg' => 120.00, 'increment' => 21.40],
    ];

    public function quoteForShipment(Shipment $shipment): Shipment
    {
        try {
            $quote = config('brightlemon.ems.rate_api_url')
                ? $this->quoteFromRateApi($shipment)
                : $this->quoteFromLocalTariff($shipment);
        } catch (EmsShipmentException $e) {
            $this->markFailed($shipment, $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            $this->markFailed($shipment, $e->getMessage());
            throw new EmsShipmentException('EMS quote request failed: '.$e->getMessage(), previous: $e);
        }

        $shipment->update([
            'shipping_price' => $quote['amount'],
            'shipping_price_currency' => $quote['currency'],
            'shipping_quote_service' => $quote['service'],
            'shipping_quote_status' => 'quoted',
            'shipping_quote_error' => null,
            'shipping_quoted_at' => now(),
        ]);

        return $shipment->refresh();
    }

    /**
     * @return array{amount: float, currency: string, service: string}
     */
    private function quoteFromRateApi(Shipment $shipment): array
    {
        try {
            $response = Http::timeout((int) config('brightlemon.ems.timeout', 30))
                ->acceptJson()
                ->post($this->rateApiUrl(), $this->buildRateRequestBody($shipment));
        } catch (\Throwable $e) {
            throw new EmsShipmentException('EMS quote API request failed: '.$e->getMessage(), previous: $e);
        }

        $payload = $response->json() ?? [];

        if (! $response->ok()) {
            throw new EmsShipmentException('EMS quote API request failed with HTTP '.$response->status());
        }

        $rate = $this->extractEmsRate($payload);

        if (! $rate) {
            $message = $this->messageFromRateErrors($payload)
                ?? 'EMS quote API response did not include an EMS rate.';

            throw new EmsShipmentException($message);
        }

        return $rate;
    }

    /**
     * @return array{amount: float, currency: string, service: string}
     */
    private function quoteFromLocalTariff(Shipment $shipment): array
    {
        $category = $this->rateCategoryFor($shipment->destination_country);
        $pricing = self::EMS_PRICING[$category] ?? null;

        if (! $pricing) {
            throw new EmsShipmentException("EMS pricing is not configured for category {$category}.");
        }

        $weight = max((float) $shipment->weight_kg, 0.1);

        if ($weight <= 0.5) {
            $amount = $pricing['half_kg'];
        } elseif ($weight <= 1.0) {
            $amount = $pricing['one_kg'];
        } else {
            $increments = ceil(($weight - 1.0) / 0.5);
            $amount = $pricing['one_kg'] + ($increments * $pricing['increment']);
        }

        return [
            'amount' => round($amount, 2),
            'currency' => 'ILS',
            'service' => 'Israel Post EMS',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRateRequestBody(Shipment $shipment): array
    {
        $destinationCountryCode = $this->countryCodeFor($shipment->destination_country);
        $recipientState = $shipment->recipient_state;

        if (in_array($destinationCountryCode, ['US', 'AU', 'CA'], true) && ! $recipientState) {
            $recipientState = 'NA';
        }

        return [
            'shipping_providers' => [
                [
                    'id' => (string) config('brightlemon.ems.rate_carrier', 'israel_post'),
                    'parameters' => [
                        'servicelevel_tokens' => ['israel_post_ems'],
                    ],
                ],
            ],
            'address_from' => [
                'country_code' => config('brightlemon.ems.sender.country_code') ?: 'IL',
                'city_name' => $shipment->sender_city,
                'postal_code' => $shipment->sender_postal_code,
            ],
            'address_to' => [
                'country_code' => $destinationCountryCode,
                'city_name' => $shipment->recipient_city,
                'postal_code' => $shipment->recipient_postal_code ?: config('brightlemon.ems.default_recipient_postal_code', '00000'),
                'state' => $recipientState,
            ],
            'parcels' => [
                [
                    'weight' => max((float) $shipment->weight_kg, 0.1),
                    'width' => (float) config('brightlemon.ems.default_parcel.width_cm', 10),
                    'height' => (float) config('brightlemon.ems.default_parcel.height_cm', 10),
                    'length' => (float) config('brightlemon.ems.default_parcel.length_cm', 10),
                ],
            ],
            'unit_of_measurement' => 'metric',
            'is_customs_declarable' => true,
            'planned_shipping_date_time' => now()->toIso8601String(),
        ];
    }

    private function rateApiUrl(): string
    {
        $url = rtrim((string) config('brightlemon.ems.rate_api_url'), " \t\n\r\0\x0B/");

        return str_ends_with($url, '/rates') ? $url : $url.'/api/v1/rates';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{amount: float, currency: string, service: string}|null
     */
    private function extractEmsRate(array $payload): ?array
    {
        $rates = Arr::get($payload, 'data', []);

        if (! is_array($rates)) {
            return null;
        }

        $selected = collect($rates)->first(function ($rate) {
            return Arr::get($rate, 'servicelevel.token') === 'israel_post_ems';
        }) ?? $rates[0] ?? null;

        if (! is_array($selected)) {
            return null;
        }

        $amount = Arr::get($selected, 'amount');
        $currency = Arr::get($selected, 'currency');

        if (! is_numeric($amount) || ! $currency) {
            return null;
        }

        return [
            'amount' => round((float) $amount, 2),
            'currency' => strtoupper((string) $currency),
            'service' => (string) (Arr::get($selected, 'servicelevel.name') ?: 'Israel Post EMS'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function messageFromRateErrors(array $payload): ?string
    {
        $errors = Arr::get($payload, 'errors', []);

        if (! is_array($errors) || $errors === []) {
            return null;
        }

        return collect($errors)
            ->map(function ($error) {
                if (is_string($error)) {
                    return $error;
                }

                if (! is_array($error)) {
                    return null;
                }

                return Arr::get($error, 'message')
                    ?? Arr::get($error, 'detail')
                    ?? Arr::get($error, 'title');
            })
            ->filter()
            ->implode(' ');
    }

    private function rateCategoryFor(string $country): int
    {
        $map = config('brightlemon.ems.rate_categories', []);
        $countryKey = strtoupper(trim($country));
        $countryCode = $this->countryCodeFor($country);
        $category = $map[$countryKey] ?? $map[$countryCode] ?? null;

        if (! $category) {
            throw new EmsShipmentException("EMS quote category is not configured for {$country}.");
        }

        return (int) $category;
    }

    private function countryCodeFor(string $country): string
    {
        $map = config('brightlemon.ems.country_codes', []);
        $key = strtoupper(trim($country));

        if (! isset($map[$key])) {
            throw new EmsShipmentException("EMS country code is not configured for {$country}.");
        }

        return $map[$key];
    }

    private function markFailed(Shipment $shipment, string $message): void
    {
        $shipment->update([
            'shipping_quote_status' => 'failed',
            'shipping_quote_error' => $message,
        ]);
    }
}
