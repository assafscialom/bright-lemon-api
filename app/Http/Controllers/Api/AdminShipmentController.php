<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EmsShipmentException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\EmsShipmentService;
use App\Services\IsraelPostQuoteService;
use App\Services\ShippingPriceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AdminShipmentController extends Controller
{
    public function emsStatus(): JsonResponse
    {
        $required = [
            'subscription_key' => 'brightlemon.ems.subscription_key',
            'username' => 'brightlemon.ems.username',
            'password' => 'brightlemon.ems.password',
            'partner_code' => 'brightlemon.ems.partner_code',
        ];

        $configured = [];
        $missing = [];

        foreach ($required as $name => $key) {
            $isSet = trim((string) config($key)) !== '';
            $configured[$name] = $isSet;

            if (! $isSet) {
                $missing[] = $name;
            }
        }

        return response()->json([
            'data' => [
                'enabled' => (bool) config('brightlemon.ems.enabled'),
                'mode' => config('brightlemon.ems.mode'),
                'api_url' => config('brightlemon.ems.api_url'),
                'configured' => $configured,
                'missing' => $missing,
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(Shipment::STATUSES)],
        ]);

        $query = Shipment::query()->latest();

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('package_number', 'like', $term)
                    ->orWhere('sender_first_name', 'like', $term)
                    ->orWhere('sender_last_name', 'like', $term)
                    ->orWhere('sender_mobile', 'like', $term)
                    ->orWhere('recipient_first_name', 'like', $term)
                    ->orWhere('recipient_last_name', 'like', $term)
                    ->orWhere('destination_country', 'like', $term)
                    ->orWhere('payment_ref', 'like', $term)
                    ->orWhere('invoice_number', 'like', $term)
                    ->orWhere('postal_ref', 'like', $term);
            });
        }

        return ShipmentResource::collection($query->get());
    }

    public function updateStatus(Request $request, Shipment $shipment): ShipmentResource|JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Shipment::STATUSES)],
        ]);

        if ($data['status'] === Shipment::STATUS_LABEL_PRINTED && ! $shipment->ems_label_content) {
            return $this->labelNotReadyResponse($shipment);
        }

        $updates = ['status' => $data['status']];

        if ($data['status'] === Shipment::STATUS_PAID && ! $shipment->paid_at) {
            $updates['paid_at'] = now();
        }

        if ($data['status'] === Shipment::STATUS_LABEL_PRINTED && ! $shipment->label_printed_at) {
            $updates['label_printed_at'] = now();
        }

        $shipment->update($updates);

        return new ShipmentResource($shipment->refresh());
    }

    public function quoteShipping(Shipment $shipment, IsraelPostQuoteService $quotes): ShipmentResource|JsonResponse
    {
        try {
            $shipment = $quotes->quoteForShipment($shipment);
        } catch (EmsShipmentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'data' => new ShipmentResource($shipment->refresh()),
            ], 502);
        }

        return new ShipmentResource($shipment->refresh());
    }

    public function recordPayment(Request $request, Shipment $shipment, EmsShipmentService $ems, ShippingPriceService $pricing): ShipmentResource|JsonResponse
    {
        $data = $request->validate([
            'payment_ref' => ['nullable', 'string', 'max:80'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            // Which drop-off branch accepted this parcel — drives the
            // Shipper / branch revenue split based on the branch's markup%.
            'drop_location_id' => ['nullable', 'integer', 'exists:shipping_drop_locations,id'],
        ]);

        if (! $shipment->shipping_quoted_at || (float) $shipment->shipping_price <= 0) {
            return response()->json([
                'message' => 'Get an EMS shipping quote before recording payment.',
                'data' => new ShipmentResource($shipment->refresh()),
            ], 422);
        }

        $dropLocationId = $data['drop_location_id'] ?? $shipment->drop_location_id;
        $updates = [
            'status' => Shipment::STATUS_PAID,
            'payment_ref' => $data['payment_ref'] ?? $shipment->payment_ref ?? 'PAY-'.$shipment->package_number,
            'invoice_number' => $data['invoice_number'] ?? $shipment->invoice_number,
            'paid_at' => $shipment->paid_at ?? now(),
            'drop_location_id' => $dropLocationId,
        ];

        // Freeze the Shipper / branch split at payment time so it survives
        // later edits to tier prices or branch markup. Only do this if we
        // actually have an assigned drop location — otherwise leave the
        // snapshot null and admin can record payment again later.
        if ($dropLocationId) {
            $split = $pricing->quote(
                (string) $shipment->destination_country,
                max((float) $shipment->weight_kg, 0.001),
                (int) $dropLocationId,
            );
            if ($split) {
                $updates['shipper_take_amount'] = $split['shipper_take'];
                $updates['drop_location_take_amount'] = $split['drop_location_take'];
                $updates['markup_percent_at_quote'] = $split['markup_percent'];
            }
        }

        $shipment->update($updates);

        try {
            $shipment = $ems->createOrderForShipment($shipment->refresh());
        } catch (EmsShipmentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'data' => new ShipmentResource($shipment->refresh()),
            ], 502);
        }

        if (! $shipment->ems_label_content) {
            return response()->json([
                'message' => 'EMS label was not created.',
                'data' => new ShipmentResource($shipment->refresh()),
            ], 502);
        }

        return new ShipmentResource($shipment->refresh());
    }

    public function markLabelPrinted(Shipment $shipment): ShipmentResource|JsonResponse
    {
        if (! $shipment->ems_label_content) {
            return $this->labelNotReadyResponse($shipment);
        }

        $shipment->update([
            'status' => Shipment::STATUS_LABEL_PRINTED,
            'label_printed_at' => $shipment->label_printed_at ?? now(),
        ]);

        return new ShipmentResource($shipment->refresh());
    }

    public function recordPostalReference(Request $request, Shipment $shipment): ShipmentResource
    {
        $data = $request->validate([
            'postal_ref' => ['required', 'string', 'max:120'],
        ]);

        $shipment->update([
            'status' => Shipment::STATUS_IN_TRANSIT,
            'postal_ref' => $data['postal_ref'],
            'label_printed_at' => $shipment->label_printed_at ?? now(),
        ]);

        return new ShipmentResource($shipment->refresh());
    }

    private function labelNotReadyResponse(Shipment $shipment): JsonResponse
    {
        return response()->json([
            'message' => 'EMS label is not ready.',
            'data' => new ShipmentResource($shipment->refresh()),
        ], 422);
    }
}
