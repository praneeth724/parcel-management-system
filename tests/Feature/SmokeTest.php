<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Parcel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Renders every screen in the application for the role that owns it.
 *
 * This is the cheapest way to catch a typo in a Blade template or a missing
 * view variable — errors that unit tests on the services would never see.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array{branch: \App\Models\Branch, admin: User, manager: User, dispatcher: User, driverUser: User, driver: \App\Models\Driver}
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
    public function public_pages_are_reachable_without_signing_in(): void
    {
        $this->get('/')->assertRedirect(route('track.index'));
        $this->get(route('track.index'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
        $this->get(route('track.show', $this->parcel->tracking_number))
            ->assertOk()
            ->assertSee($this->parcel->tracking_number);
    }

    #[Test]
    public function every_admin_screen_renders(): void
    {
        $this->actingAs($this->team['admin']);

        foreach ($this->adminRoutes() as $label => $url) {
            $this->get($url)->assertOk(); // @phpstan-ignore-line
        }
    }

    #[Test]
    public function every_manager_screen_renders(): void
    {
        $this->actingAs($this->team['manager']);

        $urls = [
            route('dashboard.manager'),
            route('parcels.index'),
            route('parcels.create'),
            route('parcels.show', $this->parcel),
            route('customers.index'),
            route('customers.create'),
            route('customers.show', $this->customer),
            route('drivers.index'),
            route('drivers.show', $this->team['driver']),
            route('branches.index'),
            route('branches.show', $this->team['branch']),
            route('branches.shipments', $this->team['branch']),
            route('users.index'),
            route('deliveries.index'),
            route('deliveries.assign'),
            route('reports.index'),
            route('reports.daily-shipments'),
            route('profile.edit'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    #[Test]
    public function every_dispatcher_screen_renders(): void
    {
        $this->actingAs($this->team['dispatcher']);

        $urls = [
            route('dashboard.dispatcher'),
            route('parcels.index'),
            route('parcels.create'),
            route('parcels.show', $this->parcel),
            route('parcels.edit', $this->parcel),
            route('parcels.label', $this->parcel),
            route('customers.index'),
            route('customers.create'),
            route('customers.show', $this->customer),
            route('customers.edit', $this->customer),
            route('drivers.index'),
            route('deliveries.index'),
            route('deliveries.assign'),
            route('reports.index'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    #[Test]
    public function every_driver_screen_renders(): void
    {
        $this->actingAs($this->team['driverUser']);

        $this->get(route('dashboard.driver'))->assertOk();
        $this->get(route('deliveries.index'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
        $this->get(route('password.change'))->assertOk();
    }

    #[Test]
    public function the_dashboard_route_sends_each_role_to_its_own_screen(): void
    {
        $expectations = [
            'admin' => 'dashboard.admin',
            'manager' => 'dashboard.manager',
            'dispatcher' => 'dashboard.dispatcher',
            'driverUser' => 'dashboard.driver',
        ];

        foreach ($expectations as $key => $routeName) {
            $this->actingAs($this->team[$key])
                ->get(route('dashboard'))
                ->assertRedirect(route($routeName));
        }
    }

    #[Test]
    public function every_report_exports_in_all_three_formats(): void
    {
        $this->actingAs($this->team['admin']);

        $reports = [
            'daily-shipments', 'monthly-revenue', 'driver-performance',
            'customer-shipments', 'deliveries',
        ];

        foreach ($reports as $report) {
            foreach (['csv', 'xlsx', 'pdf'] as $format) {
                $this->get(route('reports.export', [$report, $format]))
                    ->assertOk();
            }
        }
    }

    #[Test]
    public function a_shipping_label_can_be_downloaded_as_pdf(): void
    {
        $this->actingAs($this->team['dispatcher'])
            ->get(route('parcels.label.pdf', $this->parcel))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * @return array<string, string>
     */
    private function adminRoutes(): array
    {
        return [
            'dashboard' => route('dashboard.admin'),
            'parcels.index' => route('parcels.index'),
            'parcels.create' => route('parcels.create'),
            'parcels.show' => route('parcels.show', $this->parcel),
            'parcels.edit' => route('parcels.edit', $this->parcel),
            'parcels.label' => route('parcels.label', $this->parcel),
            'customers.index' => route('customers.index'),
            'customers.create' => route('customers.create'),
            'customers.show' => route('customers.show', $this->customer),
            'customers.edit' => route('customers.edit', $this->customer),
            'drivers.index' => route('drivers.index'),
            'drivers.create' => route('drivers.create'),
            'drivers.show' => route('drivers.show', $this->team['driver']),
            'drivers.edit' => route('drivers.edit', $this->team['driver']),
            'branches.index' => route('branches.index'),
            'branches.create' => route('branches.create'),
            'branches.show' => route('branches.show', $this->team['branch']),
            'branches.edit' => route('branches.edit', $this->team['branch']),
            'branches.shipments' => route('branches.shipments', $this->team['branch']),
            'users.index' => route('users.index'),
            'users.create' => route('users.create'),
            'users.show' => route('users.show', $this->team['dispatcher']),
            'users.edit' => route('users.edit', $this->team['dispatcher']),
            'deliveries.index' => route('deliveries.index'),
            'deliveries.assign' => route('deliveries.assign'),
            'reports.index' => route('reports.index'),
            'reports.daily' => route('reports.daily-shipments'),
            'reports.revenue' => route('reports.monthly-revenue'),
            'reports.drivers' => route('reports.driver-performance'),
            'reports.customers' => route('reports.customer-shipments'),
            'reports.deliveries' => route('reports.deliveries'),
            'profile' => route('profile.edit'),
            'password.change' => route('password.change'),
        ];
    }
}
