<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Parcel;
use App\Models\User;

/**
 * Parcels are the centre of the system, so the scoping is the most detailed.
 *
 * Drivers reach a parcel only through an assignment; management and dispatchers
 * work within their branch; Super Admins see everything.
 */
class ParcelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Parcel $parcel): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isDriver()) {
            return $this->isAssignedToDriver($user, $parcel);
        }

        return $parcel->branch_id === $user->branch_id;
    }

    public function create(User $user): bool
    {
        return ! $user->isDriver();
    }

    public function update(User $user, Parcel $parcel): bool
    {
        if ($user->isDriver()) {
            return false;
        }

        // A delivered, returned or cancelled parcel is a closed record.
        if (! $parcel->is_editable) {
            return false;
        }

        return $user->isSuperAdmin() || $parcel->branch_id === $user->branch_id;
    }

    public function delete(User $user, Parcel $parcel): bool
    {
        return $user->isManagement()
            && ($user->isSuperAdmin() || $parcel->branch_id === $user->branch_id);
    }

    public function restore(User $user, Parcel $parcel): bool
    {
        return $user->isManagement();
    }

    public function forceDelete(User $user, Parcel $parcel): bool
    {
        return false;
    }

    public function cancel(User $user, Parcel $parcel): bool
    {
        if ($user->isDriver() || ! $parcel->can_be_cancelled) {
            return false;
        }

        return $user->isSuperAdmin() || $parcel->branch_id === $user->branch_id;
    }

    /**
     * Logging a tracking event. Drivers may do this for their own parcels,
     * which is how a doorstep status update reaches the timeline.
     */
    public function addTracking(User $user, Parcel $parcel): bool
    {
        if ($parcel->status->isFinal()) {
            return false;
        }

        return $this->view($user, $parcel);
    }

    public function uploadImages(User $user, Parcel $parcel): bool
    {
        return $this->view($user, $parcel) && ! $parcel->status->isFinal();
    }

    public function printLabel(User $user, Parcel $parcel): bool
    {
        return $this->view($user, $parcel);
    }

    /**
     * Handing the parcel to a driver is a dispatch decision.
     */
    public function assignDriver(User $user, Parcel $parcel): bool
    {
        if ($user->isDriver() || $parcel->status->isFinal()) {
            return false;
        }

        return $user->isSuperAdmin() || $parcel->branch_id === $user->branch_id;
    }

    private function isAssignedToDriver(User $user, Parcel $parcel): bool
    {
        $driverId = $user->driver?->id;

        if ($driverId === null) {
            return false;
        }

        return $parcel->deliveries()->where('driver_id', $driverId)->exists();
    }
}
