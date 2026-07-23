<?php

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Enums\VehicleType;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'full_name' => fake()->name('male'),
            'phone' => '07'.fake()->randomElement(['0', '1', '2', '4', '5', '6', '7', '8'])
                .fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            // Sri Lankan plate formats: "WP CAB-1234" or "CBA-1234".
            'vehicle_number' => fake()->unique()->bothify(strtoupper('??-####')),
            'license_number' => fake()->unique()->bothify(strtoupper('B#######')),
            // A van (1000 kg) by default rather than a random type: a randomly
            // chosen motorbike caps at 20 kg, which would make any test that
            // assigns a randomly weighted parcel intermittently fail. Tests that
            // care about capacity set the type explicitly.
            'vehicle_type' => VehicleType::Van,
            'branch_id' => null,
            'photo_path' => null,
            'status' => DriverStatus::Available,
            'license_expiry' => fake()->dateTimeBetween('+6 months', '+5 years'),
            'notes' => null,
        ];
    }

    public function forBranch(Branch|int $branch): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => $branch instanceof Branch ? $branch->id : $branch,
        ]);
    }

    public function forUser(User|int $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user instanceof User ? $user->id : $user,
        ]);
    }

    public function status(DriverStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }

    public function vehicle(VehicleType $type): static
    {
        return $this->state(fn (array $attributes): array => ['vehicle_type' => $type]);
    }

    /**
     * Mixed fleet, for seeding realistic demo data.
     */
    public function randomVehicle(): static
    {
        return $this->state(fn (array $attributes): array => [
            'vehicle_type' => fake()->randomElement(VehicleType::cases()),
        ]);
    }
}
