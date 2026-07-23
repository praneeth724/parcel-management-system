<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Parcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array{branch: \App\Models\Branch, admin: \App\Models\User, manager: \App\Models\User, dispatcher: \App\Models\User, driverUser: \App\Models\User, driver: \App\Models\Driver}
     */
    private array $team;

    private Customer $customer;

    private Parcel $parcel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->makeBranchTeam();

        $this->customer = Customer::factory()
            ->forBranch($this->team['branch'])
            ->create();

        $this->parcel = Parcel::factory()
            ->forCustomer($this->customer)
            ->forBranch($this->team['branch'])
            ->create(['created_by' => $this->team['dispatcher']->id]);
    }

    #[Test]
    public function login_returns_a_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->team['admin']->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'token_type', 'user' => ['id', 'name', 'email', 'role']],
            ])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role.value', 'super_admin');

        $this->assertNotEmpty($response->json('data.token'));
    }

    #[Test]
    public function login_rejects_wrong_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->team['admin']->email,
            'password' => 'not-the-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    #[Test]
    public function a_deactivated_account_cannot_obtain_a_token(): void
    {
        $this->team['dispatcher']->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->team['dispatcher']->email,
            'password' => 'password',
        ])->assertStatus(422);
    }

    #[Test]
    public function protected_endpoints_reject_an_anonymous_caller(): void
    {
        $this->getJson('/api/v1/parcels')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function every_read_endpoint_answers_for_an_admin(): void
    {
        Sanctum::actingAs($this->team['admin']);

        $endpoints = [
            '/api/v1/auth/me',
            '/api/v1/dashboard',
            '/api/v1/parcels',
            '/api/v1/parcels/'.$this->parcel->id,
            '/api/v1/parcels/'.$this->parcel->id.'/trackings',
            '/api/v1/parcels/'.$this->parcel->id.'/qr',
            '/api/v1/deliveries',
            '/api/v1/customers',
            '/api/v1/customers/'.$this->customer->id,
            '/api/v1/customers/'.$this->customer->id.'/parcels',
            '/api/v1/drivers',
            '/api/v1/drivers/'.$this->team['driver']->id,
            '/api/v1/drivers/'.$this->team['driver']->id.'/deliveries',
            '/api/v1/branches',
            '/api/v1/branches/'.$this->team['branch']->id,
            '/api/v1/branches/'.$this->team['branch']->id.'/parcels',
            '/api/v1/reports/daily-shipments',
            '/api/v1/reports/monthly-revenue',
            '/api/v1/reports/driver-performance',
            '/api/v1/reports/customer-shipments',
            '/api/v1/reports/deliveries',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertOk();
        }
    }

    #[Test]
    public function public_tracking_needs_no_token_and_hides_private_data(): void
    {
        $response = $this->getJson('/api/v1/track/'.$this->parcel->tracking_number);

        $response->assertOk()
            ->assertJsonPath('data.tracking_number', $this->parcel->tracking_number)
            ->assertJsonStructure([
                'data' => ['status', 'sender', 'receiver', 'shipment', 'timeline', 'qr_code'],
            ]);

        // The receiver's phone and full address must never appear.
        $response->assertJsonMissing(['phone' => $this->parcel->receiver_phone]);
        $this->assertStringNotContainsString(
            $this->parcel->receiver_address,
            $response->getContent()
        );
    }

    #[Test]
    public function an_unknown_tracking_number_returns_404(): void
    {
        $this->getJson('/api/v1/track/SWT-00000000-NOPE99')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function a_parcel_can_be_booked_through_the_api(): void
    {
        Sanctum::actingAs($this->team['dispatcher']);

        $response = $this->postJson('/api/v1/parcels', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->team['branch']->id,
            'receiver_name' => 'Kamala Silva',
            'receiver_phone' => '0771234567',
            'receiver_address' => '42, Temple Road',
            'receiver_city' => 'Kandy',
            'pickup_address' => '10, Main Street',
            'parcel_type' => 'package',
            'weight' => 2.5,
            'delivery_charge' => 750,
            'payment_method' => 'cash_on_delivery',
            'priority' => 'express',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.receiver.name', 'Kamala Silva');

        $trackingNumber = $response->json('data.tracking_number');

        $this->assertMatchesRegularExpression('/^SWT-\d{8}-[A-Z0-9]{6}$/', $trackingNumber);

        // Booking must open the tracking timeline.
        $this->assertDatabaseHas('parcel_trackings', [
            'parcel_id' => $response->json('data.id'),
            'status' => 'created',
        ]);
    }

    #[Test]
    public function booking_rejects_a_non_sri_lankan_phone_and_a_zero_weight(): void
    {
        Sanctum::actingAs($this->team['dispatcher']);

        $this->postJson('/api/v1/parcels', [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->team['branch']->id,
            'receiver_name' => 'Kamala Silva',
            'receiver_phone' => '+1 555 0100',
            'receiver_address' => '42, Temple Road',
            'receiver_city' => 'Kandy',
            'pickup_address' => '10, Main Street',
            'parcel_type' => 'package',
            'weight' => 0,
            'delivery_charge' => 750,
            'payment_method' => 'cash_on_delivery',
            'priority' => 'normal',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['receiver_phone', 'weight']);
    }

    #[Test]
    public function a_driver_cannot_reach_management_endpoints(): void
    {
        Sanctum::actingAs($this->team['driverUser']);

        $this->getJson('/api/v1/customers')->assertForbidden();
        $this->getJson('/api/v1/reports/monthly-revenue')->assertForbidden();
        $this->postJson('/api/v1/parcels', [])->assertForbidden();
    }

    #[Test]
    public function logout_revokes_only_the_current_token(): void
    {
        $user = $this->team['admin'];

        $first = $user->createToken('device-a')->plainTextToken;
        $user->createToken('device-b');

        $this->withHeader('Authorization', "Bearer {$first}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(1, $user->fresh()->tokens()->count());

        // Every request in a test shares one container, so the guard still
        // holds the user it resolved a moment ago. A real request would build
        // a fresh guard; forgetting it here reproduces that.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$first}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    #[Test]
    public function pagination_respects_per_page_and_its_ceiling(): void
    {
        Parcel::factory()
            ->count(12)
            ->forCustomer($this->customer)
            ->forBranch($this->team['branch'])
            ->create();

        Sanctum::actingAs($this->team['admin']);

        $this->getJson('/api/v1/parcels?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5);

        // A request beyond the ceiling is clamped rather than honoured.
        $this->getJson('/api/v1/parcels?per_page=9999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', (int) config('courier.pagination.api_max'));
    }
}
