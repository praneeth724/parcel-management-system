<?php

namespace Database\Factories;

use App\Models\Parcel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ParcelImage>
 */
class ParcelImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parcel_id' => Parcel::factory(),
            'path' => 'parcels/images/placeholder.jpg',
            'original_name' => 'parcel.jpg',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'caption' => null,
            'uploaded_by' => null,
        ];
    }
}
