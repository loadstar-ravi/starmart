<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCancellationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_and_cancels_nothing_when_no_token_is_provided(): void
    {
        $order = Order::factory()->create();

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertUnauthorized();
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }

    public function test_returns_403_and_cancels_nothing_for_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->for($admin)->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertForbidden();
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }

    public function test_cancels_the_order_puts_its_stock_back_and_returns_it(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->create(['order_number' => 'ORD-10001']);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);
        Payment::factory()->for($order)->create();
        Sanctum::actingAs($order->user);

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('message', 'Order ORD-10001 is cancelled.');
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.status', 'cancelled');
        $response->assertJsonPath('data.payment_status', 'failed');
        $response->assertJsonCount(1, 'data.items');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_cancelling_a_paid_order_returns_it_as_refunded(): void
    {
        $order = Order::factory()->paidOnline()->create(['order_number' => 'ORD-10001']);
        Payment::factory()->for($order)->successful()->create();
        Sanctum::actingAs($order->user);

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('message', 'Order ORD-10001 is cancelled and the payment has been refunded.');
        $response->assertJsonPath('data.payment_status', 'refunded');
    }

    public function test_returns_422_and_keeps_the_stock_for_an_order_that_is_already_shipped(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->status(OrderStatus::Shipped)->create(['order_number' => 'ORD-10001']);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);
        Sanctum::actingAs($order->user);

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => 'Order ORD-10001 is shipped and can no longer be cancelled.']);
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
        $this->assertSame(3, $product->refresh()->stock);
    }

    public function test_returns_404_and_cancels_nothing_for_the_order_of_another_customer(): void
    {
        $order = Order::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'The requested resource was not found.']);
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }
}
