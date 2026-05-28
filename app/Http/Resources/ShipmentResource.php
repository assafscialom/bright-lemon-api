<?php

namespace App\Http\Resources;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $senderName = trim(implode(' ', array_filter([
            $this->sender_first_name,
            $this->sender_middle_name,
            $this->sender_last_name,
        ])));

        $recipientName = trim(implode(' ', array_filter([
            $this->recipient_first_name,
            $this->recipient_middle_name,
            $this->recipient_last_name,
        ])));

        $recipientLocation = implode(', ', array_filter([
            $this->recipient_city,
            $this->recipient_state,
            $this->destination_country,
        ]));

        $shippingPrice = $this->shipping_quoted_at ? (float) $this->shipping_price : null;
        $status = $this->status;

        if ($status === Shipment::STATUS_LABEL_PRINTED && ! $this->ems_label_content) {
            $status = $this->paid_at ? Shipment::STATUS_PAID : Shipment::STATUS_PENDING_PAYMENT;
        }

        return [
            'id' => $this->id,
            'package_number' => $this->package_number,
            'date' => $this->created_at?->format('d/m/Y'),
            'created_at' => $this->created_at?->toISOString(),
            // The drop-off branch that accepted the shipment (assigned at
            // payment). Falls back to the legacy free-text branch_name when no
            // drop location is linked yet, so older rows still show something.
            'branch' => $this->dropLocation?->name ?? $this->branch_name,
            'drop_location' => $this->dropLocation ? [
                'id' => $this->dropLocation->id,
                'code' => $this->dropLocation->code,
                'name' => $this->dropLocation->name,
            ] : null,
            'status' => $status,
            'sender' => [
                'name' => $senderName,
                'first_name' => $this->sender_first_name,
                'middle_name' => $this->sender_middle_name,
                'last_name' => $this->sender_last_name,
                'country_code' => $this->sender_country_code,
                'mobile' => $this->sender_mobile,
                'phone' => trim($this->sender_country_code.' '.$this->sender_mobile),
                'phone_normalized' => $this->sender_phone_normalized,
                'email' => $this->sender_email,
                'city' => $this->sender_city,
                'street' => $this->sender_street,
                'number' => $this->sender_number,
                'postal_code' => $this->sender_postal_code,
                'passport_number' => $this->sender_passport_number,
                'passport_expires_at' => $this->sender_passport_expires_at?->toDateString(),
            ],
            'recipient' => [
                'name' => $recipientName,
                'first_name' => $this->recipient_first_name,
                'middle_name' => $this->recipient_middle_name,
                'last_name' => $this->recipient_last_name,
                'country_code' => $this->recipient_country_code,
                'mobile' => $this->recipient_mobile,
                'phone' => trim($this->recipient_country_code.' '.$this->recipient_mobile),
                'location' => $recipientLocation,
                'country' => $this->destination_country,
                'state' => $this->recipient_state,
                'city' => $this->recipient_city,
                'street' => $this->recipient_street,
                'number' => $this->recipient_number,
                'po_box' => $this->recipient_po_box,
                'postal_code' => $this->recipient_postal_code,
            ],
            'customs' => [
                'package_type' => $this->package_type,
                'goods_type' => $this->goods_type,
                'destination' => $this->destination_country,
                'weight' => $this->weight_label,
                'weight_kg' => (float) $this->weight_kg,
                'declared_value' => (float) $this->declared_value,
                'shipping_price' => $shippingPrice,
                'shipping_price_currency' => $this->shipping_price_currency,
            ],
            'weight' => $this->weight_label,
            'weight_kg' => (float) $this->weight_kg,
            'declared_value' => (float) $this->declared_value,
            'shipping_price' => $shippingPrice,
            'shipping_price_currency' => $this->shipping_price_currency,
            'shipping_quote' => [
                'status' => $this->shipping_quote_status,
                'service' => $this->shipping_quote_service,
                'amount' => $shippingPrice,
                'currency' => $this->shipping_price_currency,
                'error' => $this->shipping_quote_error,
                'quoted_at' => $this->shipping_quoted_at?->toISOString(),
            ],
            // Split of the customer payment between Shipper and the drop
            // location, frozen at the moment the admin recorded payment.
            // Null values mean the split hasn't been computed yet (the
            // shipment hasn't been paid/assigned).
            'pricing_split' => [
                'drop_location_id' => $this->drop_location_id !== null ? (int) $this->drop_location_id : null,
                'shipper_take_amount' => $this->shipper_take_amount !== null ? (float) $this->shipper_take_amount : null,
                'drop_location_take_amount' => $this->drop_location_take_amount !== null ? (float) $this->drop_location_take_amount : null,
                'markup_percent_at_quote' => $this->markup_percent_at_quote !== null ? (float) $this->markup_percent_at_quote : null,
            ],
            'payment_ref' => $this->payment_ref,
            'invoice_number' => $this->invoice_number,
            'postal_ref' => $this->postal_ref,
            'ems' => [
                'status' => $this->ems_status,
                'tracking_number' => $this->ems_tracking_number,
                'error' => $this->ems_error,
                'created_at' => $this->ems_created_at?->toISOString(),
                'label' => $this->ems_label_content ? [
                    'format' => $this->ems_label_format,
                    'extension' => $this->ems_label_extension,
                    'content' => $this->ems_label_content,
                ] : null,
            ],
            'paid_at' => $this->paid_at?->toISOString(),
            'label_printed_at' => $this->label_printed_at?->toISOString(),
            'passport_file_url' => $this->passport_file_path
                ? asset('storage/'.$this->passport_file_path)
                : null,
            'signature_data_url' => $this->signature_data_url,
        ];
    }
}
