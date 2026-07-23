<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Order matters: branches own staff, staff sign the audit trail on parcels,
     * and deliveries need both parcels and drivers to already exist.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            UserSeeder::class,
            DriverSeeder::class,
            CustomerSeeder::class,
            ParcelSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Demo data ready. Sign in with any of these (password: password):');
        $this->command->table(
            ['Role', 'Email'],
            [
                ['Super Admin', 'admin@swifttrack.lk'],
                ['Branch Manager', 'manager.colombo@swifttrack.lk'],
                ['Dispatcher', 'dispatcher.colombo@swifttrack.lk'],
                ['Driver', 'driver1@swifttrack.lk'],
            ]
        );
    }
}
