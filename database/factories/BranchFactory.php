<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement([
            'Colombo', 'Kandy', 'Galle', 'Jaffna', 'Negombo',
            'Kurunegala', 'Matara', 'Anuradhapura', 'Batticaloa', 'Ratnapura',
        ]);

        return [
            'code' => strtoupper(Str::substr($city, 0, 3)).'-'.fake()->unique()->numerify('##'),
            'name' => "{$city} Branch",
            'address' => fake()->buildingNumber().', '.fake()->streetName().' Road',
            'city' => $city,
            'postal_code' => fake()->numerify('#####'),
            'contact_number' => '011'.fake()->numerify('#######'),
            'email' => Str::lower($city).'@swifttrack.lk',
            'manager_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
