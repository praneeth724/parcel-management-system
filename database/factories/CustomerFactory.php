<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement([
            'Colombo', 'Dehiwala', 'Moratuwa', 'Kandy', 'Galle', 'Negombo',
            'Jaffna', 'Kurunegala', 'Matara', 'Gampaha', 'Kalutara', 'Nuwara Eliya',
        ]);

        return [
            'full_name' => fake()->name(),
            // Modern Sri Lankan NIC format: 12 digits.
            'nic_passport' => fake()->unique()->numerify('############'),
            'mobile' => '07'.fake()->randomElement(['0', '1', '2', '4', '5', '6', '7', '8'])
                .fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->buildingNumber().', '.fake()->streetName().' Road',
            'city' => $city,
            'postal_code' => fake()->numerify('#####'),
            'company_name' => fake()->boolean(35) ? fake()->company().' (Pvt) Ltd' : null,
            'status' => CustomerStatus::Active,
            'branch_id' => null,
            'created_by' => null,
        ];
    }

    public function forBranch(Branch|int $branch): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => $branch instanceof Branch ? $branch->id : $branch,
        ]);
    }

    public function status(CustomerStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
