<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
            'user_id' => User::factory(),
            'total_amount' => fake()->randomFloat(2, 100, 100000),
            'status' => OrderStatus::Placed,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Cod,
            'shipping_address' => [
                'name' => fake()->name(),
                'mobile' => fake()->numerify('9#########'),
                'email' => fake()->safeEmail(),
                'address' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->word(),
                'pincode' => fake()->numerify('######'),
            ],
        ];
    }

    /**
     * Indicate that the order is in the given status.
     */
    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Indicate that the order was paid online successfully.
     */
    public function paidOnline(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Online,
            'payment_status' => PaymentStatus::Success,
        ]);
    }
}
