<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Dispatchers do the day-to-day customer work, so they get full CRUD within
 * their branch — except deletion, which stays with management.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isDriver();
    }

    public function view(User $user, Customer $customer): bool
    {
        if ($user->isDriver()) {
            return false;
        }

        return $this->withinScope($user, $customer);
    }

    public function create(User $user): bool
    {
        return ! $user->isDriver();
    }

    public function update(User $user, Customer $customer): bool
    {
        if ($user->isDriver()) {
            return false;
        }

        return $this->withinScope($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        // Soft delete is destructive enough to keep away from Dispatchers.
        return $user->isManagement() && $this->withinScope($user, $customer);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->isManagement();
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->isSuperAdmin();
    }

    public function viewShipmentHistory(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }

    /**
     * A customer with no branch was registered centrally and is shared by all.
     */
    private function withinScope(User $user, Customer $customer): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $customer->branch_id === null || $customer->branch_id === $user->branch_id;
    }
}
