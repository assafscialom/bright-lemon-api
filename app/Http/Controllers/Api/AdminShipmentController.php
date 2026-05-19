<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EmsShipmentException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\EmsShipmentService;
use App\Services\IsraelPostQuoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AdminShipmentController extends Controller
{
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

    public function recordPayment(Request $request, Shipment $shipment, EmsShipmentService $ems): ShipmentResource|JsonResponse
    {
        $data = $request->validate([
            'payment_ref' => ['nullable', 'string', 'max:80'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
        ]);

        if (! $shipment->shipping_quoted_at || (float) $shipment->shipping_price <= 0) {
            return response()->json([
                'message' => 'Get an EMS shipping quote before recording payment.',
                'data' => new ShipmentResource($shipment->refresh()),
            ], 422);
        }

        $shipment->update([
            'status' => Shipment::STATUS_PAID,
            'payment_ref' => $data['payment_ref'] ?? $shipment->payment_ref ?? 'PAY-'.$shipment->package_number,
            'invoice_number' => $data['invoice_number'] ?? $shipment->invoice_number,
            'paid_at' => $shipment->paid_at ?? now(),
        ]);

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
