<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::orderBy('id')->get();

        foreach ($branches as $branch) {
            // Whoever books at this branch is credited as the creator, which
            // makes the "created by" column on the customer profile real.
            $creator = User::query()
                ->where('branch_id', $branch->id)
                ->where('role', UserRole::Dispatcher)
                ->first();

            Customer::factory()
                ->count(12)
                ->forBranch($branch)
                ->create(['created_by' => $creator?->id]);
        }

        // A couple of edge cases so the status filter is worth using.
        Customer::factory()
            ->count(2)
            ->forBranch($branches->first())
            ->status(CustomerStatus::Inactive)
            ->create();

        Customer::factory()
            ->forBranch($branches->first())
            ->status(CustomerStatus::Blacklisted)
            ->create(['full_name' => 'Blacklisted Trader']);

        $this->command->info('  Created '.Customer::count().' customers.');
    }
}
