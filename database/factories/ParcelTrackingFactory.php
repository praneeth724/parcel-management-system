<?php

namespace Database\Factories;

use App\Enums\TrackingStatus;
use App\Models\Parcel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ParcelTracking>
 */
class ParcelTrackingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parcel_id' => Parcel::factory(),
            'status' => TrackingStatus::Created,
            'location' => fake()->city(),
            'remarks' => null,
            'updated_by' => null,
            'branch_id' => null,
            'happened_at' => now(),
        ];
    }

    public function status(TrackingStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
