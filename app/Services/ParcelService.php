<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ParcelStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TrackingStatus;
use App\Models\Parcel;
use App\Models\ParcelImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Booking, editing and moving a parcel through its lifecycle.
 *
 * Controllers stay thin: they validate input and delegate here, which keeps the
 * status rules and the tracking timeline in one place for both the web UI and
 * the API.
 */
class ParcelService
{
    public function __construct(
        private readonly TrackingService $tracking,
        private readonly QrCodeService $qrCode,
        private readonly FileUploadService $uploads,
        private readonly DriverAvailabilityService $driverAvailability,
    ) {}

    /**
     * Book a new parcel: create the record, open its timeline and mint its QR.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $images
     */
    public function create(array $data, User $actor, array $images = []): Parcel
    {
        $parcel = DB::transaction(function () use ($data, $actor): Parcel {
            $parcel = Parcel::create([
                ...$data,
                'created_by' => $actor->id,
                'status' => ParcelStatus::Pending,
                // A prepaid parcel is settled at booking; COD is collected at
                // the door and stays pending until the driver hands it in.
                'payment_status' => $this->initialPaymentStatus($data),
            ]);

            $this->tracking->recordCreation($parcel, $actor);

            return $parcel;
        });

        // Outside the transaction: writing files is not rollback-safe, and a
        // failed QR must not undo a successful booking.
        $this->qrCode->generateAndStore($parcel);

        if ($images !== []) {
            $this->attachImages($parcel, $images, $actor);
        }

        return $parcel->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Parcel $parcel, array $data, User $actor): Parcel
    {
        if (! $parcel->is_editable) {
            throw new RuntimeException(
                "A parcel that is {$parcel->status->label()} can no longer be edited."
            );
        }

        return DB::transaction(function () use ($parcel, $data, $actor): Parcel {
            $original = $parcel->only(['receiver_name', 'receiver_address', 'delivery_charge', 'priority']);

            $parcel->update($data);

            // Only log an audit event when something a customer would notice
            // actually changed.
            if ($this->hasMeaningfulChange($original, $parcel)) {
                $this->tracking->record(
                    parcel: $parcel,
                    status: TrackingStatus::Note,
                    remarks: 'Shipment details were updated by '.$actor->name.'.',
                    actor: $actor,
                );
            }

            return $parcel;
        });
    }

    /**
     * Move a parcel to a new lifecycle status, enforcing the allowed
     * transitions and writing the matching tracking event.
     */
    public function changeStatus(
        Parcel $parcel,
        ParcelStatus $target,
        User $actor,
        ?string $location = null,
        ?string $remarks = null,
    ): Parcel {
        if ($parcel->status === $target) {
            return $parcel;
        }

        if (! $parcel->status->canTransitionTo($target)) {
            throw new RuntimeException(sprintf(
                'A parcel cannot move from %s to %s.',
                $parcel->status->label(),
                $target->label()
            ));
        }

        return DB::transaction(function () use ($parcel, $target, $actor, $location, $remarks): Parcel {
            $parcel->status = $target;

            // Stamp the milestone timestamps the dashboards and reports read.
            match ($target) {
                ParcelStatus::PickedUp => $parcel->picked_up_at = now(),
                ParcelStatus::Delivered => $parcel->delivered_at = now(),
                ParcelStatus::Cancelled => $parcel->cancelled_at = now(),
                default => null,
            };

            if ($target === ParcelStatus::FailedDelivery) {
                $parcel->delivery_attempts++;
            }

            // Cash on delivery settles the moment the parcel is handed over.
            if ($target === ParcelStatus::Delivered
                && $parcel->payment_method->isCollectedOnDelivery()
                && $parcel->payment_status === PaymentStatus::Pending) {
                $parcel->payment_status = PaymentStatus::Paid;
            }

            $parcel->save();

            $this->tracking->record(
                parcel: $parcel,
                status: $target->trackingStatus(),
                location: $location,
                remarks: $remarks,
                actor: $actor,
            );

            return $parcel;
        });
    }

    /**
     * Log a warehouse milestone (Sorted, Dispatched, ...) that does not change
     * the parcel's lifecycle status, or one that does.
     */
    public function logTrackingEvent(
        Parcel $parcel,
        TrackingStatus $event,
        User $actor,
        ?string $location = null,
        ?string $remarks = null,
    ): Parcel {
        $targetStatus = $event->parcelStatus();

        // Events that imply a status change go through changeStatus so the
        // transition rules still apply.
        if ($targetStatus !== null && $targetStatus !== $parcel->status) {
            return $this->changeStatus($parcel, $targetStatus, $actor, $location, $remarks);
        }

        $this->tracking->record(
            parcel: $parcel,
            status: $event,
            location: $location,
            remarks: $remarks,
            actor: $actor,
        );

        return $parcel->refresh();
    }

    public function cancel(Parcel $parcel, User $actor, string $reason): Parcel
    {
        if (! $parcel->can_be_cancelled) {
            throw new RuntimeException(
                "A parcel that is {$parcel->status->label()} can no longer be cancelled."
            );
        }

        return DB::transaction(function () use ($parcel, $actor, $reason): Parcel {
            $parcel->update([
                'status' => ParcelStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'payment_status' => $parcel->payment_status === PaymentStatus::Paid
                    ? PaymentStatus::Refunded
                    : $parcel->payment_status,
            ]);

            // Release any driver still holding this job. The drivers are read
            // before the update, because afterwards there is no open
            // assignment left to find them by.
            $openAssignments = $parcel->deliveries()->active()->with('driver')->get();

            $parcel->deliveries()->active()->update([
                'status' => \App\Enums\DeliveryStatus::Cancelled,
                'notes' => 'Parcel cancelled: '.$reason,
            ]);

            $this->driverAvailability->refreshMany($openAssignments->pluck('driver'));

            $this->tracking->record(
                parcel: $parcel,
                status: TrackingStatus::Cancelled,
                remarks: $reason,
                actor: $actor,
            );

            return $parcel;
        });
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return array<int, ParcelImage>
     */
    public function attachImages(Parcel $parcel, array $images, User $actor): array
    {
        $max = (int) config('courier.uploads.max_parcel_images');
        $remaining = $max - $parcel->images()->count();

        if ($remaining <= 0) {
            throw new RuntimeException("This parcel already has the maximum of {$max} images.");
        }

        $stored = [];

        foreach (array_slice($images, 0, $remaining) as $image) {
            $path = $this->uploads->store($image, 'parcel_images');

            $stored[] = $parcel->images()->create([
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'size' => $image->getSize(),
                'uploaded_by' => $actor->id,
            ]);
        }

        return $stored;
    }

    public function deleteImage(ParcelImage $image): void
    {
        // The model's deleted event removes the file from disk.
        $image->delete();
    }

    /**
     * A prepaid, card or bank-transfer parcel is settled at booking time.
     *
     * @param  array<string, mixed>  $data
     */
    private function initialPaymentStatus(array $data): PaymentStatus
    {
        $method = $data['payment_method'] ?? PaymentMethod::CashOnDelivery;

        if (is_string($method)) {
            $method = PaymentMethod::from($method);
        }

        return $method->isCollectedOnDelivery()
            ? PaymentStatus::Pending
            : PaymentStatus::Paid;
    }

    /**
     * @param  array<string, mixed>  $original
     */
    private function hasMeaningfulChange(array $original, Parcel $parcel): bool
    {
        foreach ($original as $key => $value) {
            if ((string) $parcel->{$key} !== (string) $value) {
                return true;
            }
        }

        return false;
    }
}
