<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_admin_login_form_and_changes_nothing(): void
    {
        $order = Order::factory()->create();

        $response = $this->patch("/admin/orders/{$order->id}/status", ['status' => 'shipped']);

        $response->assertRedirectToRoute('admin.login');
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }

    public function test_forbids_customers_from_changing_the_status_of_their_own_order(): void
    {
        $order = Order::factory()->create();

        $response = $this->actingAs($order->user)->patch("/admin/orders/{$order->id}/status", ['status' => 'shipped']);

        $response->assertForbidden();
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }

    public function test_moves_the_order_to_a_later_status(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from("/admin/orders/{$order->id}")
            ->patch("/admin/orders/{$order->id}/status", ['status' => 'shipped']);

        $response->assertRedirect("/admin/orders/{$order->id}");
        $response->assertSessionHas('status', 'Order ORD-10001 marked as shipped.');
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
    }

    public function test_cancels_the_order_and_puts_its_stock_back(): void
    {
        $product = Product::factory()->create(['stock' => 3]);
        $order = Order::factory()->create(['order_number' => 'ORD-10001']);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from("/admin/orders/{$order->id}")
            ->patch("/admin/orders/{$order->id}/status", ['status' => 'cancelled']);

        $response->assertRedirect("/admin/orders/{$order->id}");
        $response->assertSessionHas('status', 'Order ORD-10001 cancelled.');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_cancelling_a_paid_order_reports_that_its_payment_was_refunded(): void
    {
        $order = Order::factory()->paidOnline()->create(['order_number' => 'ORD-10001']);
        Payment::factory()->for($order)->successful()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/orders/{$order->id}/status", ['status' => 'cancelled']);

        $response->assertSessionHas('status', 'Order ORD-10001 cancelled and its payment refunded.');
        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->payment_status);
    }

    public function test_refuses_to_move_an_order_back_and_keeps_its_status(): void
    {
        $order = Order::factory()->status(OrderStatus::Shipped)->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from("/admin/orders/{$order->id}")
            ->patch("/admin/orders/{$order->id}/status", ['status' => 'confirmed']);

        $response->assertRedirect("/admin/orders/{$order->id}");
        $response->assertSessionHas('error', 'Order ORD-10001 is shipped and cannot go back to confirmed.');
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
    }

    public function test_rejects_a_status_that_is_not_an_order_status(): void
    {
        $order = Order::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/orders/{$order->id}/status", ['status' => 'archived']);

        $response->assertSessionHasErrors(['status' => 'The selected status is invalid.']);
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }

    public function test_rejects_a_request_without_a_status(): void
    {
        $order = Order::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/orders/{$order->id}/status", []);

        $response->assertSessionHasErrors(['status' => 'The status field is required.']);
        $this->assertSame(OrderStatus::Placed, $order->refresh()->status);
    }

    public function test_an_unknown_order_is_not_found(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/orders/999999/status', ['status' => 'shipped']);

        $response->assertNotFound();
    }
}
