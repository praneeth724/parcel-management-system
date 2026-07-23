<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Parcel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Role-based access control.
 *
 * The point of these tests is that data from one branch must never be visible
 * to another, and that a driver can only ever reach their own work.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array{branch: Branch, admin: User, manager: User, dispatcher: User, driverUser: User, driver: Driver}
     */
    private array $home;

    private Branch $otherBranch;

    private Parcel $homeParcel;

    private Parcel $foreignParcel;

    private Customer $foreignCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->home = $this->makeBranchTeam();

        // A second branch nobody in the first branch should be able to see.
        $this->otherBranch = Branch::factory()->create();

        $homeCustomer = Customer::factory()->forBranch($this->home['branch'])->create();
        $this->foreignCustomer = Customer::factory()->forBranch($this->otherBranch)->create();

        $this->homeParcel = Parcel::factory()
            ->forCustomer($homeCustomer)
            ->forBranch($this->home['branch'])
            ->create();

        $this->foreignParcel = Parcel::factory()
            ->forCustomer($this->foreignCustomer)
            ->forBranch($this->otherBranch)
            ->create();
    }

    // -----------------------------------------------------------------
    // Branch scoping
    // -----------------------------------------------------------------

    #[Test]
    public function a_super_admin_sees_every_branch(): void
    {
        $this->actingAs($this->home['admin'])
            ->get(route('parcels.index'))
            ->assertOk()
            ->assertSee($this->homeParcel->tracking_number)
            ->assertSee($this->foreignParcel->tracking_number);
    }

    #[Test]
    public function a_branch_manager_never_sees_another_branchs_parcels(): void
    {
        $this->actingAs($this->home['manager'])
            ->get(route('parcels.index'))
            ->assertOk()
            ->assertSee($this->homeParcel->tracking_number)
            ->assertDontSee($this->foreignParcel->tracking_number);
    }

    #[Test]
    public function opening_another_branchs_parcel_directly_is_forbidden(): void
    {
        foreach (['manager', 'dispatcher'] as $role) {
            $this->actingAs($this->home[$role])
                ->get(route('parcels.show', $this->foreignParcel))
                ->assertForbidden();
        }
    }

    #[Test]
    public function opening_another_branchs_customer_directly_is_forbidden(): void
    {
        $this->actingAs($this->home['dispatcher'])
            ->get(route('customers.show', $this->foreignCustomer))
            ->assertForbidden();
    }

    #[Test]
    public function a_branch_manager_cannot_edit_another_branch(): void
    {
        $this->actingAs($this->home['manager'])
            ->get(route('branches.edit', $this->otherBranch))
            ->assertForbidden();

        // Their own branch is fine.
        $this->actingAs($this->home['manager'])
            ->get(route('branches.edit', $this->home['branch']))
            ->assertOk();
    }

    // -----------------------------------------------------------------
    // Role capabilities
    // -----------------------------------------------------------------

    #[Test]
    public function only_a_super_admin_can_create_a_branch(): void
    {
        $this->actingAs($this->home['admin'])->get(route('branches.create'))->assertOk();

        foreach (['manager', 'dispatcher'] as $role) {
            $this->actingAs($this->home[$role])
                ->get(route('branches.create'))
                ->assertForbidden();
        }
    }

    #[Test]
    public function a_dispatcher_cannot_administer_staff_accounts(): void
    {
        $this->actingAs($this->home['dispatcher'])
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($this->home['dispatcher'])
            ->get(route('users.create'))
            ->assertForbidden();
    }

    #[Test]
    public function a_dispatcher_cannot_delete_a_customer(): void
    {
        $customer = Customer::factory()->forBranch($this->home['branch'])->create();

        $this->actingAs($this->home['dispatcher'])
            ->delete(route('customers.destroy', $customer))
            ->assertForbidden();

        $this->assertNotSoftDeleted($customer);

        // A manager may.
        $this->actingAs($this->home['manager'])
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect();

        $this->assertSoftDeleted($customer);
    }

    #[Test]
    public function a_branch_manager_cannot_create_a_super_admin(): void
    {
        $this->actingAs($this->home['manager'])
            ->post(route('users.store'), [
                'name' => 'Sneaky Promotion',
                'email' => 'sneaky@swifttrack.lk',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'role' => UserRole::SuperAdmin->value,
                'branch_id' => $this->home['branch']->id,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@swifttrack.lk']);
    }

    #[Test]
    public function the_last_active_super_admin_cannot_be_deactivated(): void
    {
        $admin = $this->home['admin'];
        $other = User::factory()->superAdmin()->create();

        // With two admins, deactivating one is allowed.
        $this->actingAs($admin)
            ->post(route('users.toggle-status', $other))
            ->assertRedirect();

        $this->assertFalse($other->fresh()->is_active);

        // Now $admin is the last one — and cannot switch themselves off either.
        $this->actingAs($admin)
            ->post(route('users.toggle-status', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    // -----------------------------------------------------------------
    // Drivers
    // -----------------------------------------------------------------

    #[Test]
    public function a_driver_is_locked_out_of_every_management_screen(): void
    {
        $forbidden = [
            route('customers.index'),
            route('customers.create'),
            route('drivers.create'),
            route('branches.create'),
            route('users.index'),
            route('reports.index'),
            route('deliveries.assign'),
            route('parcels.create'),
        ];

        foreach ($forbidden as $url) {
            $this->actingAs($this->home['driverUser'])
                ->get($url)
                ->assertForbidden();
        }
    }

    #[Test]
    public function a_driver_only_sees_parcels_they_have_been_assigned(): void
    {
        // Not assigned to them yet.
        $this->actingAs($this->home['driverUser'])
            ->get(route('parcels.show', $this->homeParcel))
            ->assertForbidden();

        app(\App\Services\DeliveryService::class)->assign(
            $this->homeParcel,
            $this->home['driver'],
            $this->home['dispatcher']
        );

        // Now it is their job, so they may open it.
        $this->actingAs($this->home['driverUser'])
            ->get(route('parcels.show', $this->homeParcel->fresh()))
            ->assertOk();
    }

    #[Test]
    public function one_driver_cannot_act_on_another_drivers_delivery(): void
    {
        $otherDriverUser = User::factory()
            ->role(UserRole::Driver)
            ->forBranch($this->home['branch'])
            ->create();

        Driver::factory()
            ->forBranch($this->home['branch'])
            ->forUser($otherDriverUser)
            ->create();

        $delivery = app(\App\Services\DeliveryService::class)->assign(
            $this->homeParcel,
            $this->home['driver'],
            $this->home['dispatcher']
        );

        // The delivery belongs to the first driver, so the second may neither
        // view nor accept it.
        $this->actingAs($otherDriverUser)
            ->get(route('deliveries.show', $delivery))
            ->assertForbidden();

        $this->actingAs($otherDriverUser)
            ->post(route('deliveries.accept', $delivery))
            ->assertForbidden();
    }

    #[Test]
    public function a_manager_cannot_accept_a_delivery_on_a_drivers_behalf(): void
    {
        $delivery = app(\App\Services\DeliveryService::class)->assign(
            $this->homeParcel,
            $this->home['driver'],
            $this->home['dispatcher']
        );

        // Management can see it, but accepting is the driver's act alone.
        $this->actingAs($this->home['manager'])
            ->get(route('deliveries.show', $delivery))
            ->assertOk();

        $this->actingAs($this->home['manager'])
            ->post(route('deliveries.accept', $delivery))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Dashboards
    // -----------------------------------------------------------------

    #[Test]
    public function a_role_cannot_open_another_roles_dashboard(): void
    {
        $this->actingAs($this->home['driverUser'])
            ->get(route('dashboard.admin'))
            ->assertForbidden();

        $this->actingAs($this->home['dispatcher'])
            ->get(route('dashboard.admin'))
            ->assertForbidden();

        $this->actingAs($this->home['manager'])
            ->get(route('dashboard.driver'))
            ->assertForbidden();
    }

    #[Test]
    public function revenue_figures_are_hidden_from_dispatchers(): void
    {
        // "Total revenue" is a management-only card.
        $this->actingAs($this->home['manager'])
            ->get(route('dashboard.manager'))
            ->assertOk()
            ->assertSee('Branch revenue');

        $this->actingAs($this->home['dispatcher'])
            ->get(route('dashboard.dispatcher'))
            ->assertOk()
            ->assertDontSee('Total revenue');
    }
}
