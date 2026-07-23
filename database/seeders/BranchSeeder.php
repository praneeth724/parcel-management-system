<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Five real Sri Lankan cities, so the demo data reads like a real network.
     * Managers are attached later by UserSeeder, once those accounts exist.
     */
    public function run(): void
    {
        $branches = [
            [
                'code' => 'CMB-01',
                'name' => 'Colombo Head Office',
                'address' => 'No. 245, Galle Road, Colombo 03',
                'city' => 'Colombo',
                'postal_code' => '00300',
                'contact_number' => '0112345678',
                'email' => 'colombo@swifttrack.lk',
            ],
            [
                'code' => 'KND-02',
                'name' => 'Kandy Branch',
                'address' => 'No. 88, Peradeniya Road, Kandy',
                'city' => 'Kandy',
                'postal_code' => '20000',
                'contact_number' => '0812234567',
                'email' => 'kandy@swifttrack.lk',
            ],
            [
                'code' => 'GLL-03',
                'name' => 'Galle Branch',
                'address' => 'No. 12, Wakwella Road, Galle',
                'city' => 'Galle',
                'postal_code' => '80000',
                'contact_number' => '0912245678',
                'email' => 'galle@swifttrack.lk',
            ],
            [
                'code' => 'JAF-04',
                'name' => 'Jaffna Branch',
                'address' => 'No. 56, Hospital Road, Jaffna',
                'city' => 'Jaffna',
                'postal_code' => '40000',
                'contact_number' => '0212223456',
                'email' => 'jaffna@swifttrack.lk',
            ],
            [
                'code' => 'KUR-05',
                'name' => 'Kurunegala Branch',
                'address' => 'No. 30, Negombo Road, Kurunegala',
                'city' => 'Kurunegala',
                'postal_code' => '60000',
                'contact_number' => '0372223344',
                'email' => 'kurunegala@swifttrack.lk',
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                [...$branch, 'is_active' => true]
            );
        }

        $this->command->info('  Created '.count($branches).' branches.');
    }
}
