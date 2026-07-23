<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Driver;
use App\Models\Parcel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parcel_id' => Parcel::factory(),
            'driver_id' => Driver::factory(),
            'assigned_by' => null,
            'status' => DeliveryStatus::Assigned,
            'attempt_number' => 1,
            'assigned_at' => now(),
        ];
    }

    public function completed(?\DateTimeInterface $at = null): static
    {
        $completedAt = $at ?? now();

        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Completed,
            'accepted_at' => (clone $completedAt)->modify('-2 hours'),
            'picked_up_at' => (clone $completedAt)->modify('-90 minutes'),
            'completed_at' => $completedAt,
            'received_by' => fake()->name(),
            'delivery_location' => fake()->city(),
        ]);
    }

    public function failed(?\DateTimeInterface $at = null): static
    {
        $failedAt = $at ?? now();

        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Failed,
            'accepted_at' => (clone $failedAt)->modify('-2 hours'),
            'failed_at' => $failedAt,
            'failure_reason' => fake()->randomElement([
                'Receiver not available at the address.',
                'Address could not be located.',
                'Receiver refused the parcel.',
                'Receiver asked to reschedule delivery.',
            ]),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => fake()->randomElement([
                'Vehicle breakdown.',
                'Route is outside my assigned area.',
                'Already at full capacity for today.',
            ]),
        ]);
    }
}
