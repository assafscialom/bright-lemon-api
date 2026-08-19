<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Books the courier that collects a VIP shipment from the sender.
 *
 * Calls ISA Express, which fronts two LionWheel accounts and chooses between
 * them by `destination` — india or thailand. That is the whole reason this
 * service can refuse: a shipment bound anywhere else has no account to book
 * against, and inventing one would create a task nobody collects.
 *
 * Nothing here is allowed to fail a shipment. The customer has already been
 * told a representative will call; a courier task that could not be created
 * is a problem for the desk, not a reason to lose the order.
 */
class LionWheelPickupService
{
    public function enabled(): bool
    {
        return (bool) config('brightlemon.lionwheel.enabled')
            && filled(config('brightlemon.lionwheel.token'));
    }

    /**
     * The ISA Express account for a destination, or null when the destination
     * is not one this integration covers.
     */
    public function accountFor(?string $countryCode): ?string
    {
        $map = (array) config('brightlemon.lionwheel.destinations', []);

        return $map[strtoupper(trim((string) $countryCode))] ?? null;
    }

    /** May a VIP collection be offered for this destination at all? */
    public function supports(?string $countryCode): bool
    {
        return $this->accountFor($countryCode) !== null;
    }

    /**
     * Create the collection task.
     *
     * @return array{ok: bool, task_id?: int, public_id?: string, tracking_link?: string, error?: string}
     */
    public function createTask(Shipment $shipment): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'LionWheel integration is disabled or has no token.'];
        }

        $account = $this->accountFor($shipment->destination_country_code);

        if (! $account) {
            return [
                'ok' => false,
                'error' => sprintf(
                    'No LionWheel account for destination %s — collection is only available for India and Thailand.',
                    $shipment->destination_country_code ?: '(none)'
                ),
            ];
        }

        // The courier collects FROM the sender, so every address field here is
        // the sender's. Reading the recipient's by mistake would send a driver
        // to the wrong country.
        $payload = array_filter([
            'destination' => $account,
            'orderId' => $shipment->package_number,
            'type' => 'pickup',
            'city' => $shipment->sender_city,
            'street' => $shipment->sender_street,
            'number' => $shipment->sender_number ?: '1',
            'name' => trim($shipment->sender_first_name . ' ' . $shipment->sender_last_name),
            'phone' => $shipment->sender_phone_normalized ?: $shipment->sender_mobile,
            'boxes' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = Http::withToken((string) config('brightlemon.lionwheel.token'))
                ->timeout((int) config('brightlemon.lionwheel.timeout', 30))
                ->acceptJson()
                ->post((string) config('brightlemon.lionwheel.url'), $payload);
        } catch (Throwable $e) {
            // A timeout leaves the outcome unknown: the task may exist. Logged
            // as such rather than reported as a clean failure, because a retry
            // would risk a second courier for the same parcel.
            Log::warning('LionWheel task outcome unknown', [
                'shipment' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'No response from the courier service: ' . $e->getMessage()];
        }

        $body = $response->json() ?? [];

        if (! $response->successful() || ! ($body['success'] ?? false)) {
            Log::warning('LionWheel refused the task', [
                'shipment' => $shipment->id,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 400),
            ]);

            return [
                'ok' => false,
                'error' => sprintf('Courier service returned %d: %s',
                    $response->status(),
                    mb_substr($response->body(), 0, 200)),
            ];
        }

        return [
            'ok' => true,
            'task_id' => $body['task_id'] ?? null,
            'public_id' => $body['public_id'] ?? null,
            'tracking_link' => $body['tracking_link'] ?? null,
        ];
    }
}
