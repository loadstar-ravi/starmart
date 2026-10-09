<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_login_form_and_cancels_nothing(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-10001']);

        $response = $this->post('/orders/ORD-10001/cancel');

        $response->assertRedirectToRoute('login');
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }

    public function test_forbids_admins_and_cancels_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for($admin)->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs($admin)->post('/orders/ORD-10001/cancel');

        $response->assertForbidden();
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }

    public function test_cancels_the_order_and_puts_its_stock_back(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->create(['order_number' => 'ORD-10001']);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);

        $response = $this->actingAs($order->user)->post('/orders/ORD-10001/cancel');

        $response->assertRedirectToRoute('orders.show', 'ORD-10001');
        $response->assertSessionHas('status', 'Order ORD-10001 is cancelled.');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_cancelling_a_paid_order_tells_the_customer_it_was_refunded(): void
    {
        $order = Order::factory()->paidOnline()->create(['order_number' => 'ORD-10001']);
        Payment::factory()->for($order)->successful()->create();

        $response = $this->actingAs($order->user)->post('/orders/ORD-10001/cancel');

        $response->assertRedirectToRoute('orders.show', 'ORD-10001');
        $response->assertSessionHas('status', 'Order ORD-10001 is cancelled and your payment has been refunded.');
        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->payment_status);
    }

    public function test_refuses_to_cancel_a_shipped_order_and_keeps_its_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->status(OrderStatus::Shipped)->create(['order_number' => 'ORD-10001']);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);

        $response = $this->actingAs($order->user)->post('/orders/ORD-10001/cancel');

        $response->assertRedirectToRoute('orders.show', 'ORD-10001');
        $response->assertSessionHas('error', 'Order ORD-10001 is shipped and can no longer be cancelled.');
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
        $this->assertSame(3, $product->refresh()->stock);
    }

    public function test_cancelling_the_order_of_another_customer_is_not_found_and_cancels_nothing(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs(User::factory()->create())->post('/orders/ORD-10001/cancel');

        $response->assertNotFound();
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }
}
