<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Enums\ParcelStatus;
use App\Enums\TrackingStatus;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Parcel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The assignment lifecycle: dispatcher hands a parcel to a driver, the driver
 * accepts or rejects it, then completes or fails it at the door.
 *
 * Driver availability and parcel status are kept in step here rather than in
 * the controllers, so the web UI and the API cannot drift apart.
 */
class DeliveryService
{
    public function __construct(
        private readonly ParcelService $parcels,
        private readonly TrackingService $tracking,
        private readonly FileUploadService $uploads,
        private readonly DriverAvailabilityService $driverAvailability,
    ) {}

    /**
     * Assign a parcel to a driver.
     */
    public function assign(Parcel $parcel, Driver $driver, User $actor, ?string $notes = null): Delivery
    {
        $this->guardAssignable($parcel, $driver);

        return DB::transaction(function () use ($parcel, $driver, $actor, $notes): Delivery {
            // Retire any assignment still open on this parcel — reassignment
            // must not leave two drivers thinking the job is theirs.
            $this->releaseActiveAssignments($parcel, 'Reassigned to another driver.');

            $delivery = $parcel->deliveries()->create([
                'driver_id' => $driver->id,
                'assigned_by' => $actor->id,
                'status' => DeliveryStatus::Assigned,
                'attempt_number' => $parcel->deliveries()->count() + 1,
                'assigned_at' => now(),
                'notes' => $notes,
            ]);

            $this->driverAvailability->markBusy($driver);

            $this->tracking->record(
                parcel: $parcel,
                status: TrackingStatus::AssignedToDriver,
                location: $parcel->branch?->city,
                remarks: "Assigned to {$driver->full_name} ({$driver->driver_code}).",
                actor: $actor,
            );

            return $delivery;
        });
    }

    /**
     * Driver accepts the job.
     */
    public function accept(Delivery $delivery, User $actor): Delivery
    {
        if (! $delivery->can_be_responded_to) {
            throw new RuntimeException('This assignment has already been responded to.');
        }

        return DB::transaction(function () use ($delivery, $actor): Delivery {
            $delivery->update([
                'status' => DeliveryStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $this->tracking->record(
                parcel: $delivery->parcel,
                status: TrackingStatus::Note,
                remarks: "Driver {$delivery->driver->full_name} accepted the delivery.",
                actor: $actor,
            );

            return $delivery;
        });
    }

    /**
     * Driver declines the job; the parcel returns to the unassigned pool and
     * the driver becomes available again.
     */
    public function reject(Delivery $delivery, User $actor, string $reason): Delivery
    {
        if (! $delivery->can_be_responded_to) {
            throw new RuntimeException('This assignment has already been responded to.');
        }

        return DB::transaction(function () use ($delivery, $actor, $reason): Delivery {
            $delivery->update([
                'status' => DeliveryStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->driverAvailability->refresh($delivery->driver);

            $this->tracking->record(
                parcel: $delivery->parcel,
                status: TrackingStatus::Note,
                remarks: "Driver {$delivery->driver->full_name} declined the delivery: {$reason}",
                actor: $actor,
            );

            return $delivery;
        });
    }

    /**
     * Driver has the parcel in hand and is on the road.
     */
    public function markInTransit(Delivery $delivery, User $actor, ?string $location = null): Delivery
    {
        if ($delivery->status !== DeliveryStatus::Accepted) {
            throw new RuntimeException('Accept the delivery before marking it in transit.');
        }

        return DB::transaction(function () use ($delivery, $actor, $location): Delivery {
            $delivery->update([
                'status' => DeliveryStatus::InTransit,
                'picked_up_at' => now(),
            ]);

            $parcel = $delivery->parcel;

            // Nudge the parcel forward through whatever steps it still needs so
            // the customer-facing status matches what the driver is doing.
            if ($parcel->status === ParcelStatus::Pending) {
                $this->parcels->changeStatus($parcel, ParcelStatus::PickedUp, $actor, $location);
                $parcel->refresh();
            }

            if ($parcel->status->canTransitionTo(ParcelStatus::OutForDelivery)) {
                $this->parcels->changeStatus(
                    $parcel,
                    ParcelStatus::OutForDelivery,
                    $actor,
                    $location,
                    'Parcel is on the vehicle and out for delivery.'
                );
            }

            return $delivery->refresh();
        });
    }

    /**
     * Successful doorstep delivery, with optional proof of delivery.
     *
     * @param  array{
     *     received_by?: string|null,
     *     receiver_nic?: string|null,
     *     delivery_location?: string|null,
     *     delivery_latitude?: float|null,
     *     delivery_longitude?: float|null,
     *     cod_collected?: float|null,
     *     notes?: string|null,
     *     signature?: string|null,
     *     proof_image?: UploadedFile|null
     * }  $details
     */
    public function complete(Delivery $delivery, User $actor, array $details = []): Delivery
    {
        if (! $delivery->can_be_completed) {
            throw new RuntimeException('Only an accepted or in-transit delivery can be completed.');
        }

        // File writes happen before the transaction: a rollback cannot unwrite
        // them, so it is cleaner to have an orphaned file than a missing one.
        $signaturePath = $this->uploads->storeDataUrl($details['signature'] ?? null, 'signatures');
        $proofPath = isset($details['proof_image']) && $details['proof_image'] instanceof UploadedFile
            ? $this->uploads->store($details['proof_image'], 'proofs')
            : null;

        return DB::transaction(function () use ($delivery, $actor, $details, $signaturePath, $proofPath): Delivery {
            $delivery->update([
                'status' => DeliveryStatus::Completed,
                'completed_at' => now(),
                'received_by' => $details['received_by'] ?? $delivery->parcel->receiver_name,
                'receiver_nic' => $details['receiver_nic'] ?? null,
                'delivery_location' => $details['delivery_location'] ?? $delivery->parcel->receiver_full_address,
                'delivery_latitude' => $details['delivery_latitude'] ?? null,
                'delivery_longitude' => $details['delivery_longitude'] ?? null,
                'cod_collected' => $details['cod_collected'] ?? null,
                'notes' => $details['notes'] ?? $delivery->notes,
                'signature_path' => $signaturePath ?? $delivery->signature_path,
                'proof_image_path' => $proofPath ?? $delivery->proof_image_path,
            ]);

            $this->parcels->changeStatus(
                parcel: $delivery->parcel,
                target: ParcelStatus::Delivered,
                actor: $actor,
                location: $delivery->delivery_location,
                remarks: 'Delivered to '.$delivery->received_by.'.',
            );

            $this->driverAvailability->refresh($delivery->driver);

            return $delivery->refresh();
        });
    }

    /**
     * Failed doorstep attempt. After the configured number of attempts the
     * parcel is returned to the sender instead of being tried again.
     */
    public function markFailed(Delivery $delivery, User $actor, string $reason, ?string $location = null): Delivery
    {
        if (! $delivery->can_be_completed) {
            throw new RuntimeException('Only an accepted or in-transit delivery can be marked as failed.');
        }

        return DB::transaction(function () use ($delivery, $actor, $reason, $location): Delivery {
            $delivery->update([
                'status' => DeliveryStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => $reason,
                'delivery_location' => $location ?? $delivery->parcel->receiver_full_address,
            ]);

            $parcel = $delivery->parcel;

            $this->parcels->changeStatus(
                parcel: $parcel,
                target: ParcelStatus::FailedDelivery,
                actor: $actor,
                location: $location,
                remarks: $reason,
            );

            $parcel->refresh();

            $maxAttempts = (int) config('courier.delivery.max_attempts');

            if (config('courier.delivery.auto_return_after_max_attempts')
                && $parcel->delivery_attempts >= $maxAttempts) {
                $this->parcels->changeStatus(
                    parcel: $parcel,
                    target: ParcelStatus::Returned,
                    actor: $actor,
                    remarks: "Returned to sender after {$maxAttempts} failed delivery attempts.",
                );
            }

            $this->driverAvailability->refresh($delivery->driver);

            return $delivery->refresh();
        });
    }

    /**
     * Management pulls a job back from a driver without the driver acting.
     */
    public function cancelAssignment(Delivery $delivery, User $actor, string $reason): Delivery
    {
        if (! $delivery->status->isOpen()) {
            throw new RuntimeException('This assignment is already closed.');
        }

        return DB::transaction(function () use ($delivery, $actor, $reason): Delivery {
            $delivery->update([
                'status' => DeliveryStatus::Cancelled,
                'notes' => $reason,
            ]);

            $this->driverAvailability->refresh($delivery->driver);

            $this->tracking->record(
                parcel: $delivery->parcel,
                status: TrackingStatus::Note,
                remarks: "Assignment to {$delivery->driver->full_name} was cancelled: {$reason}",
                actor: $actor,
            );

            return $delivery;
        });
    }

    private function releaseActiveAssignments(Parcel $parcel, string $reason): void
    {
        $open = $parcel->deliveries()->active()->with('driver')->get();

        foreach ($open as $delivery) {
            $delivery->update([
                'status' => DeliveryStatus::Cancelled,
                'notes' => $reason,
            ]);
        }

        $this->driverAvailability->refreshMany($open->pluck('driver'));
    }

    private function guardAssignable(Parcel $parcel, Driver $driver): void
    {
        if ($parcel->status->isFinal()) {
            throw new RuntimeException(
                "A parcel that is {$parcel->status->label()} cannot be assigned to a driver."
            );
        }

        if ($driver->status === DriverStatus::Inactive) {
            throw new RuntimeException("{$driver->full_name} is inactive and cannot take deliveries.");
        }

        if ($driver->license_has_expired) {
            throw new RuntimeException("{$driver->full_name}'s driving licence has expired.");
        }

        if ($parcel->branch_id !== null
            && $driver->branch_id !== null
            && $parcel->branch_id !== $driver->branch_id) {
            throw new RuntimeException('The driver belongs to a different branch than this parcel.');
        }

        if ($parcel->chargeable_weight > $driver->vehicle_type->capacityKg()) {
            throw new RuntimeException(sprintf(
                'This parcel (%.2f kg) exceeds the %s capacity of %.0f kg.',
                $parcel->chargeable_weight,
                $driver->vehicle_type->label(),
                $driver->vehicle_type->capacityKg()
            ));
        }
    }
}
