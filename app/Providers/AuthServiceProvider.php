<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Capability gates for things that are not tied to a single model.
 *
 * Model-specific rules live in the policies, which Laravel discovers by naming
 * convention (App\Models\Parcel -> App\Policies\ParcelPolicy).
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Note: there is deliberately no `Gate::before` super-admin bypass here.
        //
        // A blanket "Super Admin may do anything" check would silently defeat
        // the rules that protect the system from itself — a Super Admin could
        // delete their own account, accept a delivery on a driver's behalf, or
        // edit a parcel that has already been delivered. Each policy grants
        // Super Admins what they should have, explicitly.

        Gate::define('view-reports', fn (User $user): bool => ! $user->isDriver());

        // Revenue figures are commercially sensitive, so dispatchers and
        // drivers see operational counts but never money.
        Gate::define('view-revenue', fn (User $user): bool => $user->isManagement());

        Gate::define('manage-users', fn (User $user): bool => $user->isManagement());

        Gate::define('manage-branches', fn (User $user): bool => $user->isSuperAdmin());

        Gate::define('assign-deliveries', fn (User $user): bool => ! $user->isDriver());

        // Only a Super Admin can look at, or undo, soft deletes.
        Gate::define('view-trash', fn (User $user): bool => $user->isSuperAdmin());
    }
}
