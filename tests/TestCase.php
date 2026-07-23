<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A branch staffed with one user of each role — the setup almost every
     * feature test needs before it can assert anything about scoping.
     *
     * @return array{
     *     branch: Branch,
     *     admin: User,
     *     manager: User,
     *     dispatcher: User,
     *     driverUser: User,
     *     driver: Driver
     * }
     */
    protected function makeBranchTeam(): array
    {
        $branch = Branch::factory()->create();

        $admin = User::factory()->superAdmin()->create();

        $manager = User::factory()
            ->role(UserRole::BranchManager)
            ->forBranch($branch)
            ->create();

        $branch->update(['manager_id' => $manager->id]);

        $dispatcher = User::factory()
            ->role(UserRole::Dispatcher)
            ->forBranch($branch)
            ->create();

        $driverUser = User::factory()
            ->role(UserRole::Driver)
            ->forBranch($branch)
            ->create();

        $driver = Driver::factory()
            ->forBranch($branch)
            ->forUser($driverUser)
            ->create();

        return compact('branch', 'admin', 'manager', 'dispatcher', 'driverUser', 'driver');
    }
}
