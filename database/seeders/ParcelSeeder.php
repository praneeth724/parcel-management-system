<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Enums\ParcelStatus;
use App\Enums\PaymentStatus;
use App\Enums\TrackingStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Parcel;
use App\Models\ParcelTracking;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds twelve months of shipment history.
 *
 * Rather than calling the services (which would be correct but slow for
 * thousands of rows), each parcel is walked through a plausible status path
 * here and its tracking timeline is written in one bulk insert.
 */
class ParcelSeeder extends Seeder
{
    private const MONTHS_OF_HISTORY = 12;

    /** Parcels booked per branch, per month. */
    private const PARCELS_PER_BRANCH_PER_MONTH = 14;

    public function run(): void
    {
        $branches = Branch::orderBy('id')->get();
        $trackingRows = [];

        foreach ($branches as $branch) {
            $customers = Customer::where('branch_id', $branch->id)->get();
            $drivers = Driver::where('branch_id', $branch->id)
                ->where('status', '!=', DriverStatus::Inactive)
                ->get();
            $staff = User::where('branch_id', $branch->id)
                ->whereIn('role', [UserRole::Dispatcher, UserRole::BranchManager])
                ->get();

            if ($customers->isEmpty() || $drivers->isEmpty()) {
                continue;
            }

            for ($monthsAgo = self::MONTHS_OF_HISTORY - 1; $monthsAgo >= 0; $monthsAgo--) {
                // The current month is only partly elapsed, so book fewer.
                $count = $monthsAgo === 0
                    ? (int) ceil(self::PARCELS_PER_BRANCH_PER_MONTH * (now()->day / now()->daysInMonth))
                    : self::PARCELS_PER_BRANCH_PER_MONTH;

                for ($i = 0; $i < $count; $i++) {
                    $bookedAt = $this->randomDateInMonth($monthsAgo);

                    $parcel = Parcel::factory()
                        ->forCustomer($customers->random())
                        ->forBranch($branch)
                        ->bookedAt($bookedAt)
                        ->create([
                            'created_by' => $staff->isNotEmpty() ? $staff->random()->id : null,
                        ]);

                    // progressParcel returns this parcel's timeline rows, which
                    // are collected here and bulk-inserted once at the end.
                    $trackingRows = array_merge($trackingRows, $this->progressParcel(
                        parcel: $parcel,
                        bookedAt: $bookedAt,
                        drivers: $drivers,
                        staff: $staff,
                        // Anything booked more than a week ago has had time to
                        // reach a final status; newer parcels stay mid-flight so
                        // the dashboards and the dispatch board have live work.
                        isHistorical: $monthsAgo > 0 || $bookedAt->lessThan(now()->subDays(7)),
                    ));
                }
            }
        }

        // One bulk insert beats thousands of individual saves.
        foreach (array_chunk($trackingRows, 500) as $chunk) {
            ParcelTracking::insert($chunk);
        }

        $this->syncDriverAvailability();

        $this->command->info('  Created '.Parcel::count().' parcels, '
            .Delivery::count().' deliveries and '
            .ParcelTracking::count().' tracking events.');
    }

    /**
     * Walk one parcel through a plausible lifecycle and return its timeline
     * rows.
     *
     * Returning the rows (rather than mutating a shared array by reference)
     * keeps every parcel's events together and sidesteps the reference-through-
     * a-closure threading that silently dropped rows in an earlier version.
     *
     * @param  Collection<int, Driver>  $drivers
     * @param  Collection<int, User>  $staff
     * @return array<int, array<string, mixed>>
     */
    private function progressParcel(
        Parcel $parcel,
        Carbon $bookedAt,
        Collection $drivers,
        Collection $staff,
        bool $isHistorical,
    ): array {
        $actorId = $staff->isNotEmpty() ? $staff->random()->id : null;
        $cursor = $bookedAt->copy();
        $trackingRows = [];

        $push = function (TrackingStatus $status, Carbon $at, ?string $location, ?string $remarks = null)
            use ($parcel, $actorId, &$trackingRows): void {
            $trackingRows[] = [
                'parcel_id' => $parcel->id,
                'status' => $status->value,
                'location' => $location,
                'remarks' => $remarks,
                'updated_by' => $actorId,
                'branch_id' => $parcel->branch_id,
                'happened_at' => $at->toDateTimeString(),
                'created_at' => $at->toDateTimeString(),
                'updated_at' => $at->toDateTimeString(),
            ];
        };

        $branchCity = $parcel->branch?->city;

        $push(TrackingStatus::Created, $cursor, $branchCity, 'Shipment booked and awaiting pickup.');

        // Roughly 6% of bookings never leave the Pending state.
        if (! $isHistorical && random_int(1, 100) <= 45) {
            return $trackingRows;
        }

        // A small share are cancelled before they move.
        if (random_int(1, 100) <= 4) {
            $cancelledAt = $cursor->copy()->addHours(random_int(2, 20));
            $parcel->forceFill([
                'status' => ParcelStatus::Cancelled,
                'cancelled_at' => $cancelledAt,
                'cancellation_reason' => 'Cancelled at the sender\'s request.',
                'payment_status' => $parcel->payment_status === PaymentStatus::Paid
                    ? PaymentStatus::Refunded
                    : $parcel->payment_status,
                'updated_at' => $cancelledAt,
            ])->saveQuietly();

            $push(TrackingStatus::Cancelled, $cancelledAt, $branchCity, 'Cancelled at the sender\'s request.');

            return $trackingRows;
        }

        // --- Pickup and warehouse handling ---------------------------------
        $cursor = $cursor->copy()->addHours(random_int(3, 26));
        $push(TrackingStatus::PickedUp, $cursor, $branchCity, 'Collected from the sender.');
        $pickedUpAt = $cursor->copy();

        $cursor = $cursor->copy()->addHours(random_int(2, 10));
        $push(TrackingStatus::AtWarehouse, $cursor, $branchCity, 'Received at the sorting facility.');

        $cursor = $cursor->copy()->addHours(random_int(1, 6));
        $push(TrackingStatus::Sorted, $cursor, $branchCity, 'Sorted for onward dispatch.');

        // Some parcels are still sitting at the warehouse right now.
        if (! $isHistorical && random_int(1, 100) <= 35) {
            $parcel->forceFill([
                'status' => ParcelStatus::AtWarehouse,
                'picked_up_at' => $pickedUpAt,
                'updated_at' => $cursor,
            ])->saveQuietly();

            return $trackingRows;
        }

        $cursor = $cursor->copy()->addHours(random_int(2, 14));
        $push(TrackingStatus::Dispatched, $cursor, $branchCity, 'Dispatched to the delivery hub.');

        // --- Assignment ----------------------------------------------------
        $driver = $drivers->random();
        $assignedAt = $cursor->copy()->addHours(random_int(1, 8));

        $push(
            TrackingStatus::AssignedToDriver,
            $assignedAt,
            $branchCity,
            "Assigned to {$driver->full_name} ({$driver->driver_code})."
        );

        $acceptedAt = $assignedAt->copy()->addMinutes(random_int(5, 90));
        $outForDeliveryAt = $acceptedAt->copy()->addMinutes(random_int(20, 180));

        $push(
            TrackingStatus::OutForDelivery,
            $outForDeliveryAt,
            $parcel->receiver_city,
            'Parcel is on the vehicle and out for delivery.'
        );

        // Still on the road right now.
        if (! $isHistorical && random_int(1, 100) <= 55) {
            $parcel->forceFill([
                'status' => ParcelStatus::OutForDelivery,
                'picked_up_at' => $pickedUpAt,
                'updated_at' => $outForDeliveryAt,
            ])->saveQuietly();

            Delivery::factory()->create([
                'parcel_id' => $parcel->id,
                'driver_id' => $driver->id,
                'assigned_by' => $actorId,
                'status' => DeliveryStatus::InTransit,
                'assigned_at' => $assignedAt,
                'accepted_at' => $acceptedAt,
                'picked_up_at' => $outForDeliveryAt,
            ]);

            return $trackingRows;
        }

        // --- Outcome: 86% delivered, the rest failed then returned ---------
        $succeeded = random_int(1, 100) <= 86;
        $closedAt = $outForDeliveryAt->copy()->addMinutes(random_int(30, 340));

        if ($succeeded) {
            $receivedBy = fake()->name();

            $push(
                TrackingStatus::Delivered,
                $closedAt,
                $parcel->receiver_city,
                "Delivered to {$receivedBy}."
            );

            $parcel->forceFill([
                'status' => ParcelStatus::Delivered,
                'picked_up_at' => $pickedUpAt,
                'delivered_at' => $closedAt,
                'payment_status' => PaymentStatus::Paid,
                'updated_at' => $closedAt,
            ])->saveQuietly();

            Delivery::factory()->create([
                'parcel_id' => $parcel->id,
                'driver_id' => $driver->id,
                'assigned_by' => $actorId,
                'status' => DeliveryStatus::Completed,
                'assigned_at' => $assignedAt,
                'accepted_at' => $acceptedAt,
                'picked_up_at' => $outForDeliveryAt,
                'completed_at' => $closedAt,
                'received_by' => $receivedBy,
                'delivery_location' => $parcel->receiver_full_address,
                'cod_collected' => $parcel->payment_method->isCollectedOnDelivery()
                    ? $parcel->cod_amount
                    : null,
            ]);

            return $trackingRows;
        }

        $reason = fake()->randomElement([
            'Receiver not available at the address.',
            'Address could not be located.',
            'Receiver refused the parcel.',
            'Receiver asked to reschedule delivery.',
        ]);

        $push(TrackingStatus::FailedDelivery, $closedAt, $parcel->receiver_city, $reason);

        Delivery::factory()->create([
            'parcel_id' => $parcel->id,
            'driver_id' => $driver->id,
            'assigned_by' => $actorId,
            'status' => DeliveryStatus::Failed,
            'assigned_at' => $assignedAt,
            'accepted_at' => $acceptedAt,
            'picked_up_at' => $outForDeliveryAt,
            'failed_at' => $closedAt,
            'failure_reason' => $reason,
        ]);

        // Half of the failures end up going back to the sender.
        if (random_int(1, 100) <= 50) {
            $returnedAt = $closedAt->copy()->addDays(random_int(1, 4));

            $push(
                TrackingStatus::Returned,
                $returnedAt,
                $branchCity,
                'Returned to sender after unsuccessful delivery attempts.'
            );

            $parcel->forceFill([
                'status' => ParcelStatus::Returned,
                'picked_up_at' => $pickedUpAt,
                'delivery_attempts' => random_int(2, 3),
                'updated_at' => $returnedAt,
            ])->saveQuietly();

            return $trackingRows;
        }

        $parcel->forceFill([
            'status' => ParcelStatus::FailedDelivery,
            'picked_up_at' => $pickedUpAt,
            'delivery_attempts' => 1,
            'updated_at' => $closedAt,
        ])->saveQuietly();

        return $trackingRows;
    }

    /**
     * Mark drivers who still hold an open assignment as On Delivery, so the
     * dashboard counters match the data.
     */
    private function syncDriverAvailability(): void
    {
        $busyDriverIds = Delivery::query()
            ->whereIn('status', DeliveryStatus::activeValues())
            ->distinct()
            ->pluck('driver_id');

        Driver::whereIn('id', $busyDriverIds)
            ->where('status', '!=', DriverStatus::Inactive)
            ->update(['status' => DriverStatus::OnDelivery]);
    }

    private function randomDateInMonth(int $monthsAgo): Carbon
    {
        $month = now()->subMonths($monthsAgo);

        $lastDay = $monthsAgo === 0
            ? now()->day
            : $month->daysInMonth;

        return $month->copy()
            ->startOfMonth()
            ->addDays(random_int(0, max(0, $lastDay - 1)))
            ->setTime(random_int(8, 18), random_int(0, 59));
    }
}
