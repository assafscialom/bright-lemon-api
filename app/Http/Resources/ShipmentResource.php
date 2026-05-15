<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'package_number' => $this->package_number,
            'date' => $this->created_at?->format('d/m/Y'),
            'created_at' => $this->created_at?->toISOString(),
            'branch' => $this->branch_name,
            'status' => $this->status,
            'sender' => [
                'name' => $senderName,
                'first_name' => $this->sender_first_name,
                'middle_name' => $this->sender_middle_name,
                'last_name' => $this->sender_last_name,
                'country_code' => $this->sender_country_code,
                'mobile' => $this->sender_mobile,
                'phone' => trim($this->sender_country_code.' '.$this->sender_mobile),
                'phone_normalized' => $this->sender_phone_normalized,
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
            ],
            'customs' => [
                'package_type' => $this->package_type,
                'destination' => $this->destination_country,
                'weight' => $this->weight_label,
                'weight_kg' => (float) $this->weight_kg,
                'declared_value' => (float) $this->declared_value,
                'shipping_price' => (float) $this->shipping_price,
            ],
            'weight' => $this->weight_label,
            'weight_kg' => (float) $this->weight_kg,
            'declared_value' => (float) $this->declared_value,
            'shipping_price' => (float) $this->shipping_price,
            'payment_ref' => $this->payment_ref,
            'invoice_number' => $this->invoice_number,
            'postal_ref' => $this->postal_ref,
            'paid_at' => $this->paid_at?->toISOString(),
            'label_printed_at' => $this->label_printed_at?->toISOString(),
            'passport_file_url' => $this->passport_file_path
                ? asset('storage/'.$this->passport_file_path)
                : null,
            'signature_data_url' => $this->signature_data_url,
        ];
    }
}
