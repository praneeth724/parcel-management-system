<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

/**
 * A delivery is owned by exactly one driver: only that driver may accept,
 * reject or complete it, no matter how senior another user is. Management can
 * see and reassign, but not act on someone else's behalf.
 */
class DeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Delivery $delivery): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isDriver()) {
            return $this->belongsToDriver($user, $delivery);
        }

        return $this->withinBranch($user, $delivery);
    }

    public function create(User $user): bool
    {
        return ! $user->isDriver();
    }

    public function update(User $user, Delivery $delivery): bool
    {
        if ($user->isDriver()) {
            return $this->belongsToDriver($user, $delivery) && $delivery->status->isOpen();
        }

        return $user->isSuperAdmin() || $this->withinBranch($user, $delivery);
    }

    public function delete(User $user, Delivery $delivery): bool
    {
        return $user->isManagement() && $this->view($user, $delivery);
    }

    /**
     * Accepting an assignment is strictly the assigned driver's action.
     */
    public function accept(User $user, Delivery $delivery): bool
    {
        return $user->isDriver()
            && $this->belongsToDriver($user, $delivery)
            && $delivery->can_be_responded_to;
    }

    public function reject(User $user, Delivery $delivery): bool
    {
        return $this->accept($user, $delivery);
    }

    public function complete(User $user, Delivery $delivery): bool
    {
        return $user->isDriver()
            && $this->belongsToDriver($user, $delivery)
            && $delivery->can_be_completed;
    }

    public function markFailed(User $user, Delivery $delivery): bool
    {
        return $this->complete($user, $delivery);
    }

    /**
     * Pulling a job back from a driver and giving it to someone else.
     */
    public function reassign(User $user, Delivery $delivery): bool
    {
        if ($user->isDriver()) {
            return false;
        }

        return $user->isSuperAdmin() || $this->withinBranch($user, $delivery);
    }

    private function belongsToDriver(User $user, Delivery $delivery): bool
    {
        $driverId = $user->driver?->id;

        return $driverId !== null && $delivery->driver_id === $driverId;
    }

    private function withinBranch(User $user, Delivery $delivery): bool
    {
        return $delivery->parcel?->branch_id === $user->branch_id;
    }
}
