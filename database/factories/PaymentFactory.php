<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->randomFloat(2, 100, 100000),
            'status' => PaymentStatus::Pending,
        ];
    }

    /**
     * Indicate that the payment went through.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Success,
            'transaction_reference' => 'TXN-'.fake()->unique()->numerify('##########'),
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the payment was declined.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Payment declined by the bank.',
        ]);
    }
}
