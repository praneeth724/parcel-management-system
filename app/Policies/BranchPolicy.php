<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

/**
 * Branches are structural, so only Super Admins may create or remove them.
 * A Branch Manager may edit the details of the branch they run.
 */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        // Dispatchers need the branch list to populate filters and dropdowns.
        return ! $user->isDriver();
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin() || $user->branch_id === $branch->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Branch $branch): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isBranchManager() && $user->branch_id === $branch->id;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return false;
    }

    /**
     * Reassigning drivers between branches is a Super Admin decision.
     */
    public function assignDrivers(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    public function viewShipments(User $user, Branch $branch): bool
    {
        return $this->view($user, $branch);
    }
}
