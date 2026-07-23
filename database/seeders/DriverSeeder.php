<?php

namespace Database\Seeders;

use App\Enums\DriverStatus;
use App\Enums\UserRole;
use App\Enums\VehicleType;
use App\Models\Branch;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $branches = Branch::orderBy('id')->get();

        $names = [
            'Sunil Perera', 'Kamal Ratnayake', 'Mahesh Kumara', 'Roshan De Silva',
            'Ajith Karunaratne', 'Sampath Herath', 'Lasantha Mendis', 'Buddhika Alwis',
            'Nimal Jayawardena', 'Chandana Peiris', 'Thilak Amarasinghe', 'Gayan Wijesekara',
            'Dinesh Rodrigo', 'Prasad Liyanage', 'Suresh Balasubramaniam',
        ];

        $vehicleTypes = [
            VehicleType::Motorbike, VehicleType::ThreeWheeler,
            VehicleType::Van, VehicleType::Lorry, VehicleType::Car,
        ];

        $counter = 0;

        foreach ($branches as $branchIndex => $branch) {
            // Three drivers per branch.
            for ($i = 0; $i < 3; $i++) {
                $counter++;
                $name = $names[$counter - 1] ?? fake()->name('male');

                // The first driver in every branch gets a login account so the
                // driver dashboard can be demonstrated from several branches.
                $account = null;

                if ($i === 0) {
                    $account = User::updateOrCreate(
                        ['email' => "driver{$counter}@swifttrack.lk"],
                        [
                            'name' => $name,
                            'password' => $password,
                            'role' => UserRole::Driver,
                            'branch_id' => $branch->id,
                            'phone' => '0774'.str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
                            'is_active' => true,
                            'email_verified_at' => now(),
                        ]
                    );
                }

                Driver::updateOrCreate(
                    ['license_number' => 'B'.str_pad((string) (1000000 + $counter), 7, '0', STR_PAD_LEFT)],
                    [
                        'user_id' => $account?->id,
                        'full_name' => $name,
                        'phone' => '0774'.str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
                        'email' => $account?->email ?? 'driver'.$counter.'.contact@swifttrack.lk',
                        'vehicle_number' => sprintf('%s-%04d', ['WP', 'CP', 'SP', 'NP', 'NW'][$branchIndex], 1000 + $counter),
                        'vehicle_type' => $vehicleTypes[$counter % count($vehicleTypes)],
                        'branch_id' => $branch->id,
                        'status' => DriverStatus::Available,
                        'license_expiry' => now()->addYears(random_int(1, 5)),
                    ]
                );
            }
        }

        // One inactive driver so the status filter has something to find.
        Driver::updateOrCreate(
            ['license_number' => 'B9999999'],
            [
                'full_name' => 'Retired Driver',
                'phone' => '0774999999',
                'email' => 'retired.driver@swifttrack.lk',
                'vehicle_number' => 'WP-9999',
                'vehicle_type' => VehicleType::Motorbike,
                'branch_id' => $branches->first()->id,
                'status' => DriverStatus::Inactive,
                'license_expiry' => now()->subMonths(3),
                'notes' => 'Licence expired; awaiting renewal paperwork.',
            ]
        );

        $this->command->info('  Created '.Driver::count().' drivers.');
    }
}
