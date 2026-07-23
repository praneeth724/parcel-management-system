<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Models\Driver;

/**
 * Keeps a driver's status in step with the work they are actually holding.
 *
 * Both {@see DeliveryService} (when a job is accepted, rejected or closed) and
 * {@see ParcelService} (when a parcel is cancelled out from under a driver)
 * need this, and DeliveryService already depends on ParcelService — so it lives
 * in its own class rather than being duplicated or creating a cycle.
 */
class DriverAvailabilityService
{
    /**
     * Set the driver to On Delivery if they still hold an open assignment, or
     * back to Available if they do not.
     *
     * A deliberate Off Duty or Inactive setting is never overridden — those are
     * decisions a human made, not a consequence of the workload.
     */
    public function refresh(Driver $driver): void
    {
        if (in_array($driver->status, [DriverStatus::OffDuty, DriverStatus::Inactive], strict: true)) {
            return;
        }

        $stillBusy = $driver->deliveries()
            ->whereIn('status', DeliveryStatus::activeValues())
            ->exists();

        $driver->update([
            'status' => $stillBusy ? DriverStatus::OnDelivery : DriverStatus::Available,
        ]);
    }

    /**
     * Mark a driver as occupied because a job was just handed to them.
     */
    public function markBusy(Driver $driver): void
    {
        if (in_array($driver->status, [DriverStatus::OffDuty, DriverStatus::Inactive], strict: true)) {
            return;
        }

        $driver->update(['status' => DriverStatus::OnDelivery]);
    }

    /**
     * Refresh several drivers at once, skipping duplicates.
     *
     * @param  iterable<int, Driver|null>  $drivers
     */
    public function refreshMany(iterable $drivers): void
    {
        $seen = [];

        foreach ($drivers as $driver) {
            if ($driver === null || isset($seen[$driver->id])) {
                continue;
            }

            $seen[$driver->id] = true;

            $this->refresh($driver);
        }
    }
}
