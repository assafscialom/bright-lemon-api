<?php

namespace App\Services;

use App\Exceptions\EmsShipmentException;
use App\Models\Shipment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmsShipmentService
{
    public function createOrderForShipment(Shipment $shipment): Shipment
    {
        if (! config('brightlemon.ems.enabled')) {
            throw new EmsShipmentException('EMS integration is disabled.');
        }

        if ($shipment->ems_tracking_number && $shipment->ems_label_content) {
            return $shipment;
        }

        $this->assertConfigured();
        $this->assertShipmentSenderConfigured($shipment);

        $requestBody = $this->buildShipmentRequestBody($shipment);

        try {
            $response = $this->http()
                ->withToken($this->accessToken())
                ->post($this->apiUrl('SendParcelInfo'), $requestBody);
        } catch (\Throwable $e) {
            $this->markFailed($shipment, $e->getMessage());
            throw new EmsShipmentException('EMS order request failed: '.$e->getMessage(), previous: $e);
        }

        $payload = $response->json() ?? [];

        if (! $response->ok()) {
            $message = 'EMS order request failed with HTTP '.$response->status();
            $this->markFailed($shipment, $message, $payload);
            throw new EmsShipmentException($message);
        }

        $this->throwIfErrors($shipment, $payload);
        $this->throwIfBlockingWarnings($shipment, $payload);

        $parcel = Arr::get($payload, 'Parcels.0', []);
        $trackingNumber = Arr::get($parcel, 'TrackingNumber');
        $labelContent = Arr::get($parcel, 'LabelsFiles.FileContent');
        $labelExtension = Arr::get($parcel, 'LabelsFiles.FileExtension');

        if (! $trackingNumber || ! $labelContent || ! $labelExtension) {
            $message = 'EMS order response did not include tracking number and label.';
            $this->markFailed($shipment, $message, $payload);
            throw new EmsShipmentException($message);
        }

        $shipment->update([
            'postal_ref' => $trackingNumber,
            'ems_status' => 'created',
            'ems_tracking_number' => $trackingNumber,
            'ems_label_format' => 'base64',
            'ems_label_extension' => strtolower((string) $labelExtension),
            'ems_label_content' => $labelContent,
            'ems_error' => null,
            'ems_created_at' => now(),
        ]);

        return $shipment->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShipmentRequestBody(Shipment $shipment): array
    {
        $weight = max((float) $shipment->weight_kg, 0.1);
        $invoiceNumber = (string) $shipment->invoice_number;

        return [
            'Items' => [
                [
                    'IsExport' => true,
                    'ServiceTypeCode' => 4,
                    'DeliveryTypeCode' => (int) config('brightlemon.ems.delivery_type_code', 10),
                    'OrderReference' => $invoiceNumber,
                    'ShipmentType' => (string) config('brightlemon.ems.shipment_type', '10'),
                    'ShipmentDate' => now()->toDateString(),
                    'ParcelDimensions' => [
                        'Weight' => $weight,
                    ],
                    'ImportDetails' => [
                        'CurrencyCode' => config('brightlemon.ems.currency', 'USD'),
                    ],
                    'ExportData' => [
                        'CustomsDetails' => [
                            'SenderAutonomyRegionID' => 0,
                            'IsNotDangerousGoodsDeclaration' => true,
                        ],
                        'Invoice' => [
                            'InvoiceNumber' => $invoiceNumber,
                            'InvoiceValue' => (float) $shipment->declared_value,
                            'InvoiceDate' => now()->toDateString(),
                        ],
                    ],
                    'ParcelContent' => [
                        [
                            'ItemDescription' => $shipment->package_type,
                            'ItemQuantity' => 1,
                            'ItemValue' => (float) $shipment->declared_value,
                            'ItemWeight' => $weight,
                            // Israel Post rejects the parcel with
                            // "HS_CODE of Content is required" if this is null.
                            // Map the customer's package_type to a sensible
                            // 6-digit HS code (config: brightlemon.ems.hs_codes).
                            'HsCode' => $this->hsCodeFor($shipment->package_type),
                        ],
                    ],
                    'Recipient' => $this->recipientPayload($shipment),
                    'Sender' => $this->senderPayload($shipment),
                    'ClientShipmentIdentifier' => $shipment->package_number,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recipientPayload(Shipment $shipment): array
    {
        // Israel Post's printed EMS / CN22 label only renders the `FirstName`
        // field — `LastName` is kept by the API for internal matching but never
        // appears on the PDF. Recipients with separate first/last names were
        // arriving with just the first name (e.g. "Lena" instead of
        // "Lena Rosenberg"). Concatenate into FirstName so the full name prints,
        // and keep the original LastName intact for whatever IL Post does with
        // it internally.
        $printedName = trim(
            ((string) $shipment->recipient_first_name).' '.((string) $shipment->recipient_last_name)
        );

        return [
            'FirstName' => $printedName !== '' ? $printedName : $shipment->recipient_first_name,
            'LastName' => $shipment->recipient_last_name,
            'MobilePhone' => $this->digits($shipment->recipient_mobile),
            'OtherPhone' => $this->digits($shipment->recipient_mobile),
            'Email' => null,
            'AddressLine1' => trim($shipment->recipient_street.' '.($shipment->recipient_number ?? '')),
            'AddressLine2' => $shipment->recipient_po_box ? 'P.O. Box '.$shipment->recipient_po_box : null,
            'AddressLine3' => null,
            'StreetCode' => null,
            'HouseNumber' => null,
            'HouseEntrance' => null,
            'ApartmentNum' => null,
            'PostalBox' => $shipment->recipient_po_box,
            'City' => $shipment->recipient_city,
            'CityCode' => null,
            'District' => $shipment->recipient_state,
            'ZipCode' => $shipment->recipient_postal_code ?: config('brightlemon.ems.default_recipient_postal_code', '00000'),
            'CountryCode' => $this->countryCodeFor($shipment->destination_country),
            'SubscriberNumber' => null,
            'IdType' => null,
            'IdValue' => null,
            'CountryOfIssue' => null,
            'RecipientPhoneNumber' => $this->digits($shipment->recipient_mobile),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function senderPayload(Shipment $shipment): array
    {
        $senderName = trim(implode(' ', array_filter([
            $shipment->sender_first_name,
            $shipment->sender_middle_name,
            $shipment->sender_last_name,
        ])));
        $senderPhone = $this->digits($shipment->sender_phone_normalized ?: trim($shipment->sender_country_code.' '.$shipment->sender_mobile));

        return [
            'Name' => $senderName,
            'AddressLine1' => trim($shipment->sender_street.' '.($shipment->sender_number ?? '')),
            'AddressLine2' => null,
            'StreetCode' => null,
            'HouseNumber' => null,
            'HouseEntrance' => null,
            'ApartmentNum' => null,
            'City' => $shipment->sender_city,
            'CityCode' => null,
            'District' => null,
            'ZipCode' => $shipment->sender_postal_code,
            'CountryCode' => config('brightlemon.ems.sender.country_code', 'IL'),
            'Phone' => $senderPhone,
            'OtherPhone' => $senderPhone,
            // Israel Post requires SenderEmail and 422s without it. The form
            // marks it optional, so fall back to a configured default address
            // when the customer left it blank (config:
            // brightlemon.ems.sender.fallback_email).
            'Email' => $shipment->sender_email
                ?: config('brightlemon.ems.sender.fallback_email'),
            'PartnerCode' => config('brightlemon.ems.partner_code'),
        ];
    }

    private function accessToken(): string
    {
        try {
            $response = $this->http()
                ->post($this->apiUrl('GetToken'), [
                    'Username' => config('brightlemon.ems.username'),
                    'Password' => config('brightlemon.ems.password'),
                ]);
        } catch (\Throwable $e) {
            throw new EmsShipmentException('EMS token request failed: '.$e->getMessage(), previous: $e);
        }

        if (! $response->ok() || ! $response->json('AccessToken')) {
            throw new EmsShipmentException('EMS authorization failed with HTTP '.$response->status());
        }

        return (string) $response->json('AccessToken');
    }

    private function apiUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('brightlemon.ems.api_url'), " \t\n\r\0\x0B/");

        return $baseUrl.'/Export/'.trim($path, " \t\n\r\0\x0B/");
    }

    private function subscriptionKey(): string
    {
        return trim((string) config('brightlemon.ems.subscription_key'));
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout((int) config('brightlemon.ems.timeout', 30));
        $subscriptionKey = $this->subscriptionKey();

        return $subscriptionKey === ''
            ? $request
            : $request->withHeader('Ocp-Apim-Subscription-Key', $subscriptionKey);
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

    /**
     * Resolve the HS (Harmonized System) tariff code for a given package_type.
     * IL Post requires this on every ParcelContent line — we pick a code that
     * matches the customer's selected package type (Gift / Merchandise / etc.)
     * and fall back to the configured default for unknown types.
     */
    private function hsCodeFor(?string $packageType): string
    {
        $byType = (array) config('brightlemon.ems.hs_codes.by_type', []);
        $default = (string) config('brightlemon.ems.hs_codes.default', '999900');

        if ($packageType !== null && isset($byType[$packageType])) {
            $code = trim((string) $byType[$packageType]);
            if ($code !== '') {
                return $code;
            }
        }
        return $default;
    }

    private function assertConfigured(): void
    {
        $required = [
            'brightlemon.ems.api_url',
            'brightlemon.ems.subscription_key',
            'brightlemon.ems.username',
            'brightlemon.ems.password',
            'brightlemon.ems.partner_code',
        ];

        foreach ($required as $key) {
            if (! config($key)) {
                throw new EmsShipmentException("EMS configuration is missing: {$key}");
            }
        }
    }

    private function assertShipmentSenderConfigured(Shipment $shipment): void
    {
        $required = [
            'sender_first_name',
            'sender_last_name',
            'sender_mobile',
            'sender_city',
            'sender_street',
            'sender_postal_code',
        ];

        foreach ($required as $key) {
            if (! trim((string) $shipment->{$key})) {
                throw new EmsShipmentException("EMS sender information is missing: {$key}");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function throwIfErrors(Shipment $shipment, array $payload): void
    {
        $errors = Arr::get($payload, 'Errors', []);

        if (! is_array($errors) || $errors === []) {
            return;
        }

        $message = $this->messagesFrom($errors, 'ErrorCode');
        $this->markFailed($shipment, $message, $payload);

        throw new EmsShipmentException($message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function throwIfBlockingWarnings(Shipment $shipment, array $payload): void
    {
        $warnings = Arr::get($payload, 'Warnings', []);

        if (! is_array($warnings) || $warnings === []) {
            return;
        }

        $allowed = array_map('strval', config('brightlemon.ems.allowed_warning_codes', []));
        $blocking = array_values(array_filter($warnings, function ($warning) use ($allowed) {
            return ! in_array((string) Arr::get($warning, 'WarningCode'), $allowed, true);
        }));

        if ($blocking === []) {
            return;
        }

        $message = $this->messagesFrom($blocking, 'WarningCode');
        $this->markFailed($shipment, $message, $payload);

        throw new EmsShipmentException($message);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function messagesFrom(array $items, string $codeKey): string
    {
        return collect($items)
            ->map(fn (array $item) => trim(sprintf(
                '%s: %s',
                Arr::get($item, $codeKey, 'EMS'),
                Arr::get($item, 'Description', 'Unknown EMS error')
            )))
            ->implode('; ');
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function markFailed(Shipment $shipment, string $message, ?array $payload = null): void
    {
        Log::warning('EMS order creation failed', [
            'shipment_id' => $shipment->id,
            'package_number' => $shipment->package_number,
            'message' => $message,
            'response' => $payload,
        ]);

        $shipment->update([
            'ems_status' => 'failed',
            'ems_error' => $message,
        ]);
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
