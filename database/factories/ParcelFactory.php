<?php

namespace Database\Factories;

use App\Enums\DeliveryPriority;
use App\Enums\ParcelStatus;
use App\Enums\ParcelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Parcel>
 */
class ParcelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement([
            'Colombo', 'Kandy', 'Galle', 'Jaffna', 'Negombo', 'Kurunegala',
            'Matara', 'Anuradhapura', 'Batticaloa', 'Ratnapura', 'Badulla', 'Trincomalee',
        ]);

        $priority = fake()->randomElement(DeliveryPriority::cases());
        $type = fake()->randomElement(ParcelType::cases());
        $weight = fake()->randomFloat(2, 0.25, 25);
        $method = fake()->randomElement(PaymentMethod::cases());

        $charge = app(PricingService::class)->calculate($weight, $priority, $type);

        return [
            'customer_id' => Customer::factory(),
            'branch_id' => null,
            'receiver_name' => fake()->name(),
            'receiver_phone' => '07'.fake()->randomElement(['0', '1', '2', '4', '5', '6', '7', '8'])
                .fake()->numerify('#######'),
            'receiver_address' => fake()->buildingNumber().', '.fake()->streetName().' Road',
            'receiver_city' => $city,
            'receiver_postal_code' => fake()->numerify('#####'),
            'pickup_address' => fake()->buildingNumber().', '.fake()->streetName().' Street',
            'parcel_type' => $type,
            'weight' => $weight,
            'length_cm' => fake()->boolean(70) ? fake()->randomFloat(1, 10, 80) : null,
            'width_cm' => fake()->boolean(70) ? fake()->randomFloat(1, 10, 60) : null,
            'height_cm' => fake()->boolean(70) ? fake()->randomFloat(1, 5, 50) : null,
            'delivery_charge' => $charge,
            'cod_amount' => $method->isCollectedOnDelivery() ? fake()->randomFloat(2, 500, 25000) : 0,
            'payment_method' => $method,
            'payment_status' => $method->isCollectedOnDelivery()
                ? PaymentStatus::Pending
                : PaymentStatus::Paid,
            'priority' => $priority,
            'status' => ParcelStatus::Pending,
            'special_instructions' => fake()->boolean(25) ? fake()->sentence() : null,
            'delivery_attempts' => 0,
        ];
    }

    public function forCustomer(Customer|int $customer): static
    {
        return $this->state(fn (array $attributes): array => [
            'customer_id' => $customer instanceof Customer ? $customer->id : $customer,
        ]);
    }

    public function forBranch(Branch|int $branch): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => $branch instanceof Branch ? $branch->id : $branch,
        ]);
    }

    public function status(ParcelStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }

    /**
     * Back-date the booking so the monthly charts have history to draw.
     */
    public function bookedAt(\DateTimeInterface $when): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ParcelStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'delivered_at' => now(),
        ]);
    }
}
