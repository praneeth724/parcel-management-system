<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

/**
 * The four staff roles described in the specification.
 *
 * Roles are hierarchical for convenience checks: a Super Admin implicitly holds
 * every capability of the roles beneath it. Fine grained decisions still go
 * through the policies in app/Policies.
 */
enum UserRole: string
{
    use HasEnumHelpers;

    case SuperAdmin = 'super_admin';
    case BranchManager = 'branch_manager';
    case Dispatcher = 'dispatcher';
    case Driver = 'driver';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::BranchManager => 'Branch Manager',
            self::Dispatcher => 'Dispatcher',
            self::Driver => 'Driver',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::BranchManager => 'primary',
            self::Dispatcher => 'info',
            self::Driver => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'bi-shield-lock',
            self::BranchManager => 'bi-building',
            self::Dispatcher => 'bi-clipboard-check',
            self::Driver => 'bi-truck',
        };
    }

    /**
     * Lower number means broader authority.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 0,
            self::BranchManager => 1,
            self::Dispatcher => 2,
            self::Driver => 3,
        };
    }

    /**
     * Route name of the dashboard this role lands on after logging in.
     */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::SuperAdmin => 'dashboard.admin',
            self::BranchManager => 'dashboard.manager',
            self::Dispatcher => 'dashboard.dispatcher',
            self::Driver => 'dashboard.driver',
        };
    }

    /**
     * Roles that operate inside a single branch and therefore require one.
     */
    public function requiresBranch(): bool
    {
        return $this !== self::SuperAdmin;
    }

    public function isAtLeast(self $role): bool
    {
        return $this->rank() <= $role->rank();
    }
}
