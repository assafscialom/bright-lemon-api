<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrightLemonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_lists_shipments_by_sender_phone(): void
    {
        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'Registered')
            ->assertJsonPath('data.sender.phone_normalized', '+9720501234567')
            ->json('data');

        $this->assertNotEmpty($shipment['package_number']);

        $this->getJson('/api/v1/shipments?country_code=+972&mobile=0501234567')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.package_number', $shipment['package_number']);
    }

    public function test_admin_can_record_payment_for_shipment(): void
    {
        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/v1/admin/shipments/{$shipment['id']}/payment", [
            'invoice_number' => 'INV-1001',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Paid')
            ->assertJsonPath('data.invoice_number', 'INV-1001')
            ->assertJsonPath('data.payment_ref', 'PAY-'.$shipment['package_number']);
    }

    public function test_demo_otp_verification_rejects_wrong_codes(): void
    {
        $this->postJson('/api/v1/auth/otp/verify', [
            'country_code' => '+972',
            'mobile' => '0501234567',
            'context' => 'tracking',
            'code' => '000000',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid verification code.');
    }

    private function shipmentPayload(): array
    {
        return [
            'sender' => [
                'first_name' => 'Assaf',
                'middle_name' => '',
                'last_name' => 'Cohen',
                'country_code' => '+972',
                'mobile' => '0501234567',
            ],
            'passport' => [
                'number' => 'A12345678',
                'expires_at' => now()->addYear()->toDateString(),
            ],
            'signature_data_url' => 'data:image/png;base64,abc',
            'customs' => [
                'package_type' => 'Gift',
                'destination' => 'Germany',
                'weight' => '1 – 2 kg',
                'declared_value' => 120,
            ],
            'recipient' => [
                'first_name' => 'Anna',
                'middle_name' => '',
                'last_name' => 'Muller',
                'state' => 'Berlin',
                'city' => 'Berlin',
                'street' => 'Main',
                'number' => '12',
                'po_box' => '',
                'country_code' => '+49',
                'mobile' => '3011223344',
            ],
        ];
    }
}
