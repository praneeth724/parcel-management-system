<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Who may administer staff accounts.
 *
 * Super Admins manage everyone. Branch Managers manage the Dispatchers and
 * Drivers in their own branch but cannot touch other managers or admins.
 * Dispatchers and Drivers have no user administration rights at all.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function view(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Anyone may view their own record.
        if ($user->id === $target->id) {
            return true;
        }

        return $user->isBranchManager() && $this->sharesBranch($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->isManagement();
    }

    public function update(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isBranchManager()) {
            return false;
        }

        return $this->sharesBranch($user, $target) && $this->managesRole($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        // Nobody may delete themselves — that would orphan the session and can
        // lock the last Super Admin out of the system.
        if ($user->id === $target->id) {
            return false;
        }

        return $this->update($user, $target);
    }

    public function restore(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }

    /**
     * Toggling `is_active` follows the same rules as editing.
     */
    public function toggleStatus(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->update($user, $target);
    }

    /**
     * Only a Super Admin may hand out or take away roles, so a Branch Manager
     * cannot promote themselves.
     */
    public function assignRole(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }

    private function sharesBranch(User $user, User $target): bool
    {
        return $user->branch_id !== null && $user->branch_id === $target->branch_id;
    }

    /**
     * A Branch Manager may only administer roles below their own.
     */
    private function managesRole(User $user, User $target): bool
    {
        return $target->hasRole(UserRole::Dispatcher, UserRole::Driver);
    }
}
