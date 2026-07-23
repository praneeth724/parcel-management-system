<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

/**
 * Driver records are managed by Super Admins and Branch Managers. Dispatchers
 * may read them (they need to pick someone to assign a parcel to) but not edit
 * them. A Driver may only see their own record.
 */
class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Driver $driver): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isDriver()) {
            return $driver->user_id === $user->id;
        }

        return $driver->branch_id === $user->branch_id;
    }

    public function create(User $user): bool
    {
        return $user->isManagement();
    }

    public function update(User $user, Driver $driver): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBranchManager() && $driver->branch_id === $user->branch_id;
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }

    public function restore(User $user, Driver $driver): bool
    {
        return $user->isManagement();
    }

    public function forceDelete(User $user, Driver $driver): bool
    {
        return false;
    }

    public function toggleStatus(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }

    /**
     * The delivery list on a driver's profile.
     */
    public function viewDeliveries(User $user, Driver $driver): bool
    {
        return $this->view($user, $driver);
    }

    public function viewPerformance(User $user, Driver $driver): bool
    {
        return $this->view($user, $driver);
    }
}
