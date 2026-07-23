<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Memoised so a seeder creating dozens of users does not pay for bcrypt
     * at 12 rounds every single time.
     */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Dispatcher,
            'branch_id' => null,
            'phone' => '07'.fake()->randomElement(['0', '1', '2', '4', '5', '6', '7', '8']).fake()->numerify('#######'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $role,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::SuperAdmin,
            'branch_id' => null,
        ]);
    }

    public function forBranch(Branch|int $branch): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => $branch instanceof Branch ? $branch->id : $branch,
        ]);
    }
}
