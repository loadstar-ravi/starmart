<?php

namespace Tests\Feature\Models;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

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
