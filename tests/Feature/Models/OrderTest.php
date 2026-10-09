<?php

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_numbers_count_up_from_ord_10001(): void
    {
        $this->assertSame('ORD-10001', Order::numberFor(1));
        $this->assertSame('ORD-10042', Order::numberFor(42));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function ordersAndWhetherTheyAwaitPayment(): array
    {
        return [
            'online and not paid yet' => [
                ['payment_method' => PaymentMethod::Online, 'payment_status' => PaymentStatus::Pending],
                true,
            ],
            'online with a failed payment' => [
                ['payment_method' => PaymentMethod::Online, 'payment_status' => PaymentStatus::Failed],
                true,
            ],
            'online and paid' => [
                ['payment_method' => PaymentMethod::Online, 'payment_status' => PaymentStatus::Success],
                false,
            ],
            'online and refunded' => [
                ['payment_method' => PaymentMethod::Online, 'payment_status' => PaymentStatus::Refunded],
                false,
            ],
            'online, not paid, but cancelled' => [
                [
                    'payment_method' => PaymentMethod::Online,
                    'payment_status' => PaymentStatus::Pending,
                    'status' => OrderStatus::Cancelled,
                ],
                false,
            ],
            'cash on delivery' => [
                ['payment_method' => PaymentMethod::Cod, 'payment_status' => PaymentStatus::Pending],
                false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('ordersAndWhetherTheyAwaitPayment')]
    public function test_only_unpaid_online_orders_that_are_not_cancelled_await_payment(array $attributes, bool $expected): void
    {
        $order = Order::factory()->create($attributes);

        $this->assertSame($expected, $order->isAwaitingPayment());
    }

    public function test_latest_payment_is_the_most_recent_attempt(): void
    {
        $order = Order::factory()->create();
        Payment::factory()->for($order)->failed()->create();
        $retry = Payment::factory()->for($order)->successful()->create();

        $this->assertCount(2, $order->payments);
        $this->assertTrue($order->latestPayment->is($retry));
    }

    public function test_shipping_address_is_stored_and_read_back_as_an_array(): void
    {
        $address = [
            'name' => 'Priya Sharma',
            'mobile' => '9876543210',
            'email' => 'priya@example.com',
            'address' => '12 MG Road',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'pincode' => '302001',
        ];

        $order = Order::factory()->create(['shipping_address' => $address]);

        $this->assertSame($address, $order->fresh()->shipping_address);
    }

    public function test_a_user_who_has_orders_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create();

        try {
            $user->delete();
            $this->fail('Deleting a user with orders should violate the foreign key.');
        } catch (QueryException) {
            $this->assertModelExists($user);
        }
    }
}
