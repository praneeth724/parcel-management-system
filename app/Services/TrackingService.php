<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TrackingStatus;
use App\Models\Parcel;
use App\Models\ParcelTracking;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Writes the parcel tracking timeline.
 *
 * Every status change in the system funnels through here so the audit trail is
 * complete by construction rather than by everyone remembering to log.
 */
class TrackingService
{
    /**
     * Append an event to a parcel's timeline.
     */
    public function record(
        Parcel $parcel,
        TrackingStatus $status,
        ?string $location = null,
        ?string $remarks = null,
        ?User $actor = null,
        ?Carbon $happenedAt = null,
    ): ParcelTracking {
        return $parcel->trackings()->create([
            'status' => $status,
            'location' => $location ?? $this->defaultLocation($parcel, $status),
            'remarks' => $remarks,
            'updated_by' => $actor?->id,
            'branch_id' => $actor?->branch_id ?? $parcel->branch_id,
            'happened_at' => $happenedAt ?? now(),
        ]);
    }

    /**
     * The opening "Package Created" event, written when a parcel is booked.
     */
    public function recordCreation(Parcel $parcel, ?User $actor = null): ParcelTracking
    {
        return $this->record(
            parcel: $parcel,
            status: TrackingStatus::Created,
            location: $parcel->branch?->city ?? $parcel->receiver_city,
            remarks: 'Shipment booked and awaiting pickup.',
            actor: $actor,
        );
    }

    /**
     * Where an event happened when the caller did not say.
     */
    private function defaultLocation(Parcel $parcel, TrackingStatus $status): ?string
    {
        return match ($status) {
            TrackingStatus::Delivered,
            TrackingStatus::FailedDelivery,
            TrackingStatus::OutForDelivery => $parcel->receiver_city,
            default => $parcel->branch?->city,
        };
    }

    /**
     * The public timeline, with staff names replaced by a generic label.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function publicTimeline(Parcel $parcel): \Illuminate\Support\Collection
    {
        return $parcel->trackings
            ->map(fn (ParcelTracking $event): array => [
                'status' => $event->status->value,
                'label' => $event->status->label(),
                'color' => $event->status->color(),
                'icon' => $event->status->icon(),
                'location' => $event->location,
                'remarks' => $event->remarks,
                'happened_at' => $event->happened_at,
                'updated_by' => $event->public_actor_name,
            ])
            ->reverse()
            ->values();
    }
}
