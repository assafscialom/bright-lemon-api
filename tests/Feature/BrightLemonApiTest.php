<?php

namespace Tests\Feature;

use App\Models\ShippingDropLocation;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AdminTokenService;
use App\Services\EmsShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrightLemonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_lists_shipments_by_sender_phone(): void
    {
        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'Registered')
            ->assertJsonPath('data.declared_value', 120)
            ->assertJsonPath('data.shipping_price', null)
            ->assertJsonPath('data.shipping_quote.status', null)
            ->assertJsonPath('data.sender.phone_normalized', '+9720501234567')
            ->json('data');

        $this->assertNotEmpty($shipment['package_number']);

        $this->getJson('/api/v1/shipments?country_code=+972&mobile=0501234567')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.package_number', $shipment['package_number']);
    }

    public function test_admin_payment_requires_ems_integration_to_create_label(): void
    {
        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->json('data');

        $token = $this->superAdminToken();

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/shipping-quote")
            ->assertOk()
            ->assertJsonPath('data.shipping_quote.status', 'quoted')
            ->assertJsonPath('data.shipping_quote.currency', 'ILS');

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/payment", [
            'invoice_number' => 'INV-1001',
        ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'EMS integration is disabled.')
            ->assertJsonPath('data.status', 'Paid')
            ->assertJsonPath('data.invoice_number', 'INV-1001')
            ->assertJsonPath('data.payment_ref', 'PAY-'.$shipment['package_number']);
    }

    public function test_admin_payment_requires_shipping_quote(): void
    {
        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->json('data');

        $this->withToken($this->superAdminToken())->postJson("/api/v1/admin/shipments/{$shipment['id']}/payment", [
            'invoice_number' => 'INV-1001',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Get an EMS shipping quote before recording payment.');
    }

    public function test_admin_can_request_ems_quote_from_rate_api(): void
    {
        config([
            'brightlemon.ems.rate_api_url' => 'https://rates.example.test',
            'brightlemon.ems.sender.city' => 'Tel Aviv',
            'brightlemon.ems.sender.postal_code' => '6100001',
        ]);

        Http::fake([
            'https://rates.example.test/api/v1/rates' => Http::response([
                'success' => true,
                'message' => 'Shipping rates',
                'data' => [
                    [
                        'amount' => 118.75,
                        'currency' => 'ILS',
                        'provider' => 'israel_post',
                        'servicelevel' => [
                            'name' => 'Israel Post EMS',
                            'token' => 'israel_post_ems',
                        ],
                    ],
                ],
                'errors' => [],
            ]),
        ]);

        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->json('data');

        $this->withToken($this->superAdminToken())->postJson("/api/v1/admin/shipments/{$shipment['id']}/shipping-quote")
            ->assertOk()
            ->assertJsonPath('data.declared_value', 120)
            ->assertJsonPath('data.shipping_price', 118.75)
            ->assertJsonPath('data.shipping_price_currency', 'ILS')
            ->assertJsonPath('data.shipping_quote.service', 'Israel Post EMS');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment['id'],
            'shipping_price' => 118.75,
            'shipping_price_currency' => 'ILS',
            'shipping_quote_status' => 'quoted',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rates.example.test/api/v1/rates'
                && $request['shipping_providers'][0]['id'] === 'israel_post'
                && $request['shipping_providers'][0]['parameters']['servicelevel_tokens'][0] === 'israel_post_ems'
                && $request['address_to']['country_code'] === 'DE'
                && $request['parcels'][0]['weight'] === 2.0;
        });
    }

    public function test_admin_payment_creates_ems_order_when_enabled(): void
    {
        config([
            'brightlemon.ems.enabled' => true,
            'brightlemon.ems.api_url' => 'https://ems.example.test',
            'brightlemon.ems.subscription_key' => 'subscription-key',
            'brightlemon.ems.username' => 'ems-user',
            'brightlemon.ems.password' => 'ems-password',
            'brightlemon.ems.partner_code' => '400327',
            'brightlemon.ems.sender.name' => 'Ship Home',
            'brightlemon.ems.sender.address_line_1' => 'Dizengoff 100',
            'brightlemon.ems.sender.city' => 'Tel Aviv',
            'brightlemon.ems.sender.postal_code' => '6100001',
            'brightlemon.ems.sender.phone' => '+972544522993',
            'brightlemon.ems.sender.email' => 'ops@example.com',
        ]);

        Http::fake([
            'https://ems.example.test/Export/GetToken' => Http::response([
                'IsSuccess' => true,
                'AccessToken' => 'token-123',
                'AccessTokenType' => 'Bearer',
                'ExpireIn' => 3600,
            ]),
            'https://ems.example.test/Export/SendParcelInfo' => Http::response([
                'Errors' => [],
                'Warnings' => [],
                'Parcels' => [
                    [
                        'TrackingNumber' => 'EE123456789IL',
                        'LabelsFiles' => [
                            'FileContent' => base64_encode('%PDF label'),
                            'FileExtension' => 'PDF',
                        ],
                    ],
                ],
            ]),
        ]);

        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->json('data');

        $token = $this->superAdminToken();

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/shipping-quote")
            ->assertOk();

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/payment", [
            'invoice_number' => 'INV-EMS-1001',
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-EMS-1001')
            ->assertJsonPath('data.postal_ref', 'EE123456789IL')
            ->assertJsonPath('data.ems.status', 'created')
            ->assertJsonPath('data.ems.tracking_number', 'EE123456789IL')
            ->assertJsonPath('data.ems.label.extension', 'pdf');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment['id'],
            'ems_status' => 'created',
            'ems_tracking_number' => 'EE123456789IL',
            'postal_ref' => 'EE123456789IL',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://ems.example.test/Export/SendParcelInfo'
                && $request['Items'][0]['ServiceTypeCode'] === 4
                && $request['Items'][0]['OrderReference'] === 'INV-EMS-1001'
                && $request['Items'][0]['Recipient']['CountryCode'] === 'DE';
        });
    }

    public function test_admin_payment_fails_if_ems_service_returns_without_label(): void
    {
        config(['brightlemon.ems.enabled' => true]);

        $this->mock(EmsShipmentService::class, function ($mock) {
            $mock->shouldReceive('createOrderForShipment')
                ->once()
                ->andReturnUsing(fn (Shipment $shipment) => $shipment->refresh());
        });

        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->json('data');

        $token = $this->superAdminToken();

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/shipping-quote")
            ->assertOk();

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/payment", [
            'invoice_number' => 'INV-NO-LABEL',
        ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'EMS label was not created.')
            ->assertJsonPath('data.ems.label', null)
            ->assertJsonPath('data.ems.tracking_number', null);
    }

    public function test_admin_cannot_mark_label_printed_before_ems_label_is_ready(): void
    {
        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->json('data');

        $token = $this->superAdminToken();

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/shipping-quote")
            ->assertOk();

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/payment", [
            'invoice_number' => 'INV-1001',
        ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'EMS integration is disabled.')
            ->assertJsonPath('data.status', Shipment::STATUS_PAID)
            ->assertJsonPath('data.ems.label', null);

        $this->withToken($token)->postJson("/api/v1/admin/shipments/{$shipment['id']}/label-printed")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'EMS label is not ready.')
            ->assertJsonPath('data.status', Shipment::STATUS_PAID);

        $this->withToken($token)->patchJson("/api/v1/admin/shipments/{$shipment['id']}/status", [
            'status' => Shipment::STATUS_LABEL_PRINTED,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'EMS label is not ready.')
            ->assertJsonPath('data.status', Shipment::STATUS_PAID);
    }

    public function test_label_printed_status_is_reported_as_paid_when_ems_label_is_missing(): void
    {
        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload())
            ->assertCreated()
            ->json('data');

        $model = Shipment::findOrFail($shipment['id']);
        $model->update([
            'status' => Shipment::STATUS_LABEL_PRINTED,
            'invoice_number' => 'INV-1001',
            'paid_at' => now(),
            'label_printed_at' => now(),
            'ems_label_content' => null,
        ]);

        $this->withToken($this->superAdminToken())->getJson('/api/v1/admin/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.status', Shipment::STATUS_PAID);
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
