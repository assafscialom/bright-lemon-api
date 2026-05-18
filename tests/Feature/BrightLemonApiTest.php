<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ShippingDropLocation;
use App\Services\AdminTokenService;
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

        $this->withToken($this->superAdminToken())->postJson("/api/v1/admin/shipments/{$shipment['id']}/payment", [
            'invoice_number' => 'INV-1001',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Paid')
            ->assertJsonPath('data.invoice_number', 'INV-1001')
            ->assertJsonPath('data.payment_ref', 'PAY-'.$shipment['package_number']);
    }

    public function test_admin_otp_rejects_phone_numbers_without_superadmin_user(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'country_code' => '+972',
            'mobile' => '0501234567',
            'context' => 'admin',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This phone number is not allowed to access admin.');
    }

    public function test_superadmin_can_verify_otp_with_phone_number(): void
    {
        User::factory()->create([
            'phone' => '+9720501234567',
            'role' => User::ROLE_SUPERADMIN,
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'country_code' => '+972',
            'mobile' => '0501234567',
            'context' => 'admin',
            'code' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_SUPERADMIN)
            ->assertJsonStructure(['token']);
    }

    public function test_active_drop_location_phone_can_verify_admin_otp(): void
    {
        ShippingDropLocation::create([
            'code' => 'TLV-002',
            'name' => 'Tel Aviv Partner Drop',
            'country' => 'Israel',
            'city' => 'Tel Aviv',
            'address_line_1' => 'Allenby 1',
            'phone' => '+972544522993',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->postJson('/api/v1/auth/otp/send', [
            'country_code' => '+972',
            'mobile' => '0544522993',
            'context' => 'admin',
        ])
            ->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'country_code' => '+972',
            'mobile' => '0544522993',
            'context' => 'admin',
            'code' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_ADMIN)
            ->assertJsonPath('user.name', 'Tel Aviv Partner Drop')
            ->assertJsonStructure(['token']);
    }

    public function test_drop_location_admin_can_access_shipments_but_not_manage_locations(): void
    {
        $location = ShippingDropLocation::create([
            'code' => 'TLV-003',
            'name' => 'Tel Aviv Operations Drop',
            'country' => 'Israel',
            'city' => 'Tel Aviv',
            'address_line_1' => 'Rothschild 1',
            'phone' => '+9720544522993',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $token = app(AdminTokenService::class)->issueForDropLocation($location, '+9720544522993');

        $this->withToken($token)->getJson('/api/v1/admin/shipments')
            ->assertOk();

        $this->withToken($token)->getJson('/api/v1/admin/drop-locations')
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin access denied.');
    }

    public function test_superadmin_can_manage_shipping_drop_locations(): void
    {
        $token = $this->superAdminToken();

        $location = $this->withToken($token)->postJson('/api/v1/admin/drop-locations', [
            'code' => 'TLV-001',
            'name' => 'Tel Aviv Central Drop',
            'country' => 'Israel',
            'city' => 'Tel Aviv',
            'address_line_1' => 'Dizengoff 100',
            'address_line_2' => 'Floor 1',
            'contact_name' => 'Dana',
            'phone' => '+9720544522993',
            'email' => 'tlv@example.com',
            'opening_hours' => 'Sun-Thu 09:00-18:00',
            'notes' => 'Ask for the shipping desk.',
            'is_active' => true,
            'sort_order' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'TLV-001')
            ->assertJsonPath('data.is_active', true)
            ->json('data');

        $this->withToken($token)->putJson("/api/v1/admin/drop-locations/{$location['id']}", [
            'code' => 'TLV-001',
            'name' => 'Tel Aviv Main Drop',
            'country' => 'Israel',
            'city' => 'Tel Aviv',
            'address_line_1' => 'Dizengoff 100',
            'address_line_2' => null,
            'contact_name' => 'Dana',
            'phone' => '+9720544522993',
            'email' => 'tlv-main@example.com',
            'opening_hours' => 'Sun-Thu 10:00-18:00',
            'notes' => null,
            'is_active' => true,
            'sort_order' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tel Aviv Main Drop')
            ->assertJsonPath('data.sort_order', 5);

        $this->withToken($token)->getJson('/api/v1/admin/drop-locations?search=main')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'TLV-001');

        $this->withToken($token)->deleteJson("/api/v1/admin/drop-locations/{$location['id']}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas(ShippingDropLocation::class, [
            'code' => 'TLV-001',
            'is_active' => false,
        ]);
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

    private function superAdminToken(): string
    {
        $user = User::factory()->create([
            'phone' => '+9720501234567',
            'role' => User::ROLE_SUPERADMIN,
        ]);

        return app(AdminTokenService::class)->issue($user);
    }
}
