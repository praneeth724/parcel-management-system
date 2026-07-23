<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Enums\ParcelStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TrackingStatus;
use App\Models\Customer;
use App\Models\Parcel;
use App\Services\DeliveryService;
use App\Services\ParcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The rules that make the tracking timeline trustworthy: legal status
 * transitions, an audit entry for every change, and driver availability that
 * stays in step with the work.
 */
class ParcelLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array{branch: \App\Models\Branch, admin: \App\Models\User, manager: \App\Models\User, dispatcher: \App\Models\User, driverUser: \App\Models\User, driver: \App\Models\Driver}
     */
    private array $team;

    private Customer $customer;

    private ParcelService $parcels;

    private DeliveryService $deliveries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->makeBranchTeam();
        $this->customer = Customer::factory()->forBranch($this->team['branch'])->create();
        $this->parcels = app(ParcelService::class);
        $this->deliveries = app(DeliveryService::class);
    }

    #[Test]
    public function booking_generates_a_unique_tracking_number_and_opens_the_timeline(): void
    {
        $parcel = $this->bookParcel();

        $this->assertMatchesRegularExpression('/^SWT-\d{8}-[A-Z0-9]{6}$/', $parcel->tracking_number);
        $this->assertSame(ParcelStatus::Pending, $parcel->status);

        $this->assertDatabaseHas('parcel_trackings', [
            'parcel_id' => $parcel->id,
            'status' => TrackingStatus::Created->value,
        ]);

        // A second booking must not collide with the first.
        $other = $this->bookParcel();
        $this->assertNotSame($parcel->tracking_number, $other->tracking_number);
    }

    #[Test]
    public function the_expected_delivery_date_follows_the_priority_sla(): void
    {
        $sameDay = $this->bookParcel(['priority' => 'same_day']);
        $express = $this->bookParcel(['priority' => 'express']);

        $this->assertTrue($sameDay->expected_delivery_at->isToday());
        $this->assertTrue($express->expected_delivery_at->greaterThan($sameDay->expected_delivery_at));
    }

    #[Test]
    public function a_prepaid_parcel_is_settled_at_booking_but_cash_on_delivery_is_not(): void
    {
        $prepaid = $this->bookParcel(['payment_method' => PaymentMethod::Card->value]);
        $cod = $this->bookParcel(['payment_method' => PaymentMethod::CashOnDelivery->value]);

        $this->assertSame(PaymentStatus::Paid, $prepaid->payment_status);
        $this->assertSame(PaymentStatus::Pending, $cod->payment_status);
    }

    #[Test]
    public function an_illegal_status_transition_is_refused(): void
    {
        $parcel = $this->bookParcel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot move from Pending to Delivered');

        $this->parcels->changeStatus($parcel, ParcelStatus::Delivered, $this->team['dispatcher']);
    }

    #[Test]
    public function each_legal_status_change_appends_exactly_one_tracking_event(): void
    {
        $parcel = $this->bookParcel();

        $this->assertSame(1, $parcel->trackings()->count());

        $this->parcels->changeStatus($parcel, ParcelStatus::PickedUp, $this->team['dispatcher']);
        $this->assertSame(2, $parcel->fresh()->trackings()->count());

        $this->parcels->changeStatus($parcel->fresh(), ParcelStatus::AtWarehouse, $this->team['dispatcher']);
        $this->assertSame(3, $parcel->fresh()->trackings()->count());

        $this->assertSame(ParcelStatus::AtWarehouse, $parcel->fresh()->status);
        $this->assertNotNull($parcel->fresh()->picked_up_at);
    }

    #[Test]
    public function a_delivered_parcel_can_no_longer_be_edited(): void
    {
        $parcel = $this->deliverParcel();

        $this->assertTrue($parcel->status->isFinal());
        $this->assertFalse($parcel->is_editable);

        $this->expectException(RuntimeException::class);

        $this->parcels->update($parcel, ['receiver_name' => 'Someone Else'], $this->team['dispatcher']);
    }

    #[Test]
    public function completing_a_delivery_settles_a_cash_on_delivery_payment(): void
    {
        $parcel = $this->deliverParcel(['payment_method' => PaymentMethod::CashOnDelivery->value]);

        $this->assertSame(ParcelStatus::Delivered, $parcel->status);
        $this->assertSame(PaymentStatus::Paid, $parcel->payment_status);
        $this->assertNotNull($parcel->delivered_at);
    }

    #[Test]
    public function assigning_a_parcel_occupies_the_driver_and_completing_it_frees_them(): void
    {
        $driver = $this->team['driver'];
        $parcel = $this->bookParcel();

        $delivery = $this->deliveries->assign($parcel, $driver, $this->team['dispatcher']);

        $this->assertSame(DriverStatus::OnDelivery, $driver->fresh()->status);
        $this->assertSame(DeliveryStatus::Assigned, $delivery->status);

        $this->deliveries->accept($delivery, $this->team['driverUser']);
        $this->deliveries->markInTransit($delivery->fresh(), $this->team['driverUser']);
        $this->deliveries->complete($delivery->fresh(), $this->team['driverUser'], [
            'received_by' => 'Kamala Silva',
        ]);

        $this->assertSame(DriverStatus::Available, $driver->fresh()->status);
        $this->assertSame(ParcelStatus::Delivered, $parcel->fresh()->status);
    }

    #[Test]
    public function a_rejected_assignment_frees_the_driver_and_returns_the_parcel_to_the_pool(): void
    {
        $driver = $this->team['driver'];
        $parcel = $this->bookParcel();

        $delivery = $this->deliveries->assign($parcel, $driver, $this->team['dispatcher']);
        $this->deliveries->reject($delivery, $this->team['driverUser'], 'Vehicle breakdown.');

        $this->assertSame(DeliveryStatus::Rejected, $delivery->fresh()->status);
        $this->assertSame(DriverStatus::Available, $driver->fresh()->status);

        // Back in the unassigned queue, ready to be given to someone else.
        $this->assertTrue(
            Parcel::query()->unassigned()->whereKey($parcel->id)->exists()
        );
    }

    #[Test]
    public function reassigning_closes_the_previous_assignment(): void
    {
        $driver = $this->team['driver'];
        $parcel = $this->bookParcel();

        $first = $this->deliveries->assign($parcel, $driver, $this->team['dispatcher']);

        $second = $this->deliveries->assign($parcel->fresh(), $driver, $this->team['dispatcher']);

        $this->assertSame(DeliveryStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(DeliveryStatus::Assigned, $second->status);
        $this->assertSame(2, $second->attempt_number);

        // Exactly one assignment is open at any moment.
        $this->assertSame(1, $parcel->deliveries()->active()->count());
    }

    #[Test]
    public function a_parcel_too_heavy_for_the_vehicle_cannot_be_assigned(): void
    {
        $driver = $this->team['driver'];
        $driver->update(['vehicle_type' => \App\Enums\VehicleType::Motorbike]); // 20 kg limit

        $parcel = $this->bookParcel(['weight' => 150]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds the Motorbike capacity');

        $this->deliveries->assign($parcel, $driver, $this->team['dispatcher']);
    }

    #[Test]
    public function a_driver_with_an_expired_licence_cannot_be_assigned(): void
    {
        $driver = $this->team['driver'];
        $driver->update(['license_expiry' => now()->subDay()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('licence has expired');

        $this->deliveries->assign($this->bookParcel(), $driver, $this->team['dispatcher']);
    }

    #[Test]
    public function a_parcel_is_returned_to_sender_after_the_maximum_failed_attempts(): void
    {
        config(['courier.delivery.max_attempts' => 2]);

        $driver = $this->team['driver'];
        $parcel = $this->bookParcel();

        foreach (range(1, 2) as $attempt) {
            $delivery = $this->deliveries->assign($parcel->fresh(), $driver, $this->team['dispatcher']);
            $this->deliveries->accept($delivery, $this->team['driverUser']);
            $this->deliveries->markInTransit($delivery->fresh(), $this->team['driverUser']);
            $this->deliveries->markFailed(
                $delivery->fresh(),
                $this->team['driverUser'],
                'Receiver not available.'
            );
        }

        $parcel->refresh();

        $this->assertSame(2, $parcel->delivery_attempts);
        $this->assertSame(ParcelStatus::Returned, $parcel->status);
    }

    #[Test]
    public function cancelling_releases_the_driver_and_refunds_a_settled_payment(): void
    {
        $driver = $this->team['driver'];
        $parcel = $this->bookParcel(['payment_method' => PaymentMethod::Card->value]);

        $this->deliveries->assign($parcel, $driver, $this->team['dispatcher']);

        $this->parcels->cancel($parcel->fresh(), $this->team['manager'], 'Sender changed their mind.');

        $parcel->refresh();

        $this->assertSame(ParcelStatus::Cancelled, $parcel->status);
        $this->assertSame(PaymentStatus::Refunded, $parcel->payment_status);
        $this->assertSame(DriverStatus::Available, $driver->fresh()->status);
        $this->assertSame(0, $parcel->deliveries()->active()->count());
    }

    #[Test]
    public function warehouse_only_events_are_logged_without_moving_the_parcel(): void
    {
        $parcel = $this->bookParcel();
        $this->parcels->changeStatus($parcel, ParcelStatus::PickedUp, $this->team['dispatcher']);
        $parcel->refresh();

        $before = $parcel->status;

        $this->parcels->logTrackingEvent(
            $parcel,
            TrackingStatus::Sorted,
            $this->team['dispatcher'],
            remarks: 'Sorted for the northern route.'
        );

        $this->assertSame($before, $parcel->fresh()->status);
        $this->assertDatabaseHas('parcel_trackings', [
            'parcel_id' => $parcel->id,
            'status' => TrackingStatus::Sorted->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function bookParcel(array $overrides = []): Parcel
    {
        return $this->parcels->create(
            data: [
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
                'cod_amount' => 0,
                'payment_method' => PaymentMethod::CashOnDelivery->value,
                'priority' => 'normal',
                ...$overrides,
            ],
            actor: $this->team['dispatcher'],
        );
    }

    /**
     * Book a parcel and walk it all the way to Delivered.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function deliverParcel(array $overrides = []): Parcel
    {
        $parcel = $this->bookParcel($overrides);

        $delivery = $this->deliveries->assign($parcel, $this->team['driver'], $this->team['dispatcher']);
        $this->deliveries->accept($delivery, $this->team['driverUser']);
        $this->deliveries->markInTransit($delivery->fresh(), $this->team['driverUser']);
        $this->deliveries->complete($delivery->fresh(), $this->team['driverUser'], [
            'received_by' => 'Kamala Silva',
        ]);

        return $parcel->fresh();
    }
}
