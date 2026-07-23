<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // --- Super Admin: no branch, sees the whole network ----------------
        User::updateOrCreate(
            ['email' => 'admin@swifttrack.lk'],
            [
                'name' => 'Ashan Wickramasinghe',
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'branch_id' => null,
                'phone' => '0771000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $branches = Branch::orderBy('id')->get();

        // --- One manager and one or two dispatchers per branch -------------
        foreach ($branches as $index => $branch) {
            $slug = Str::lower(Str::before($branch->city, ' '));

            $manager = User::updateOrCreate(
                ['email' => "manager.{$slug}@swifttrack.lk"],
                [
                    'name' => $this->managerNames()[$index] ?? fake()->name(),
                    'password' => $password,
                    'role' => UserRole::BranchManager,
                    'branch_id' => $branch->id,
                    'phone' => '077200000'.($index + 1),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            // Close the loop: the branch points at its manager.
            $branch->update(['manager_id' => $manager->id]);

            User::updateOrCreate(
                ['email' => "dispatcher.{$slug}@swifttrack.lk"],
                [
                    'name' => $this->dispatcherNames()[$index] ?? fake()->name(),
                    'password' => $password,
                    'role' => UserRole::Dispatcher,
                    'branch_id' => $branch->id,
                    'phone' => '077300000'.($index + 1),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        // A second dispatcher at head office, plus one deactivated account so
        // the activate/deactivate feature has something to demonstrate.
        $head = $branches->first();

        User::updateOrCreate(
            ['email' => 'dispatcher2.colombo@swifttrack.lk'],
            [
                'name' => 'Tharindu Jayasuriya',
                'password' => $password,
                'role' => UserRole::Dispatcher,
                'branch_id' => $head->id,
                'phone' => '0773000099',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'former.staff@swifttrack.lk'],
            [
                'name' => 'Ruwan Dissanayake',
                'password' => $password,
                'role' => UserRole::Dispatcher,
                'branch_id' => $head->id,
                'phone' => '0773000098',
                'is_active' => false,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('  Created '.User::count().' staff accounts.');
    }

    /**
     * @return array<int, string>
     */
    private function managerNames(): array
    {
        return [
            'Dilhani Rajapaksa',
            'Nuwan Bandara',
            'Chamari Fernando',
            'Kumaran Sivalingam',
            'Sanjaya Weerasinghe',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function dispatcherNames(): array
    {
        return [
            'Ishara Gunawardena',
            'Pradeep Ekanayake',
            'Nadeesha Silva',
            'Arjun Thevarajah',
            'Malithi Senanayake',
        ];
    }
}
