<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_login_form(): void
    {
        Order::factory()->create(['order_number' => 'ORD-10001']);

        $this->get('/orders')->assertRedirectToRoute('login');
        $this->get('/orders/ORD-10001')->assertRedirectToRoute('login');
    }

    public function test_forbids_admins(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->for($admin)->create(['order_number' => 'ORD-10001']);

        $this->actingAs($admin)->get('/orders')->assertForbidden();
        $this->actingAs($admin)->get('/orders/ORD-10001')->assertForbidden();
    }

    public function test_lists_the_customers_orders_newest_first_with_their_amounts_and_statuses(): void
    {
        $customer = User::factory()->create();
        Order::factory()->for($customer)->create([
            'order_number' => 'ORD-10001',
            'total_amount' => 799,
            'created_at' => '2026-10-07 09:00:00',
        ]);
        $newer = Order::factory()->for($customer)->paidOnline()->status(OrderStatus::Shipped)->create([
            'order_number' => 'ORD-10002',
            'total_amount' => 1250,
            'created_at' => '2026-10-09 09:00:00',
        ]);
        OrderItem::factory()->for($newer)->create(['quantity' => 3]);

        $response = $this->actingAs($customer)->get('/orders');

        $response->assertOk();
        $response->assertSeeInOrder([
            'ORD-10002', '9 Oct 2026', '3 items', '1,250.00', 'Shipped', 'Paid',
            'ORD-10001', '7 Oct 2026', '799.00', 'Placed', 'Pending',
        ]);
    }

    public function test_does_not_list_the_orders_of_other_customers(): void
    {
        Order::factory()->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs(User::factory()->create())->get('/orders');

        $response->assertOk();
        $response->assertDontSee('ORD-10001');
        $response->assertSee('You have not placed any orders yet.');
    }

    public function test_shows_ten_orders_per_page(): void
    {
        $customer = User::factory()->create();
        Order::factory()->for($customer)->count(11)->sequence(fn ($sequence) => [
            'order_number' => sprintf('ORD-%05d', 20001 + $sequence->index),
            'created_at' => now()->subDays(11 - $sequence->index),
        ])->create();

        $firstPage = $this->actingAs($customer)->get('/orders');
        $secondPage = $this->actingAs($customer)->get('/orders?page=2');

        $firstPage->assertSee('ORD-20011');
        $firstPage->assertSee('ORD-20002');
        $firstPage->assertDontSee('ORD-20001');
        $secondPage->assertSee('ORD-20001');
        $secondPage->assertDontSee('ORD-20002');
    }

    public function test_offers_to_pay_only_for_online_orders_that_are_not_paid_yet(): void
    {
        $customer = User::factory()->create();
        Order::factory()->for($customer)->create(['order_number' => 'ORD-10001', 'payment_method' => PaymentMethod::Online]);
        Order::factory()->for($customer)->create(['order_number' => 'ORD-10002', 'payment_method' => PaymentMethod::Cod]);

        $response = $this->actingAs($customer)->get('/orders');

        $response->assertOk();
        $response->assertSee(route('payments.create', 'ORD-10001'));
        $response->assertDontSee(route('payments.create', 'ORD-10002'));
    }

    public function test_shows_the_details_of_one_order(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-10001',
            'total_amount' => 110780,
            'payment_method' => PaymentMethod::Cod,
            'created_at' => '2026-10-09 10:30:00',
            'shipping_address' => [
                'name' => 'Priya Sharma',
                'mobile' => '9876543210',
                'email' => 'priya@example.com',
                'address' => '12 MG Road',
                'city' => 'Jaipur',
                'state' => 'Rajasthan',
                'pincode' => '302001',
            ],
        ]);
        OrderItem::factory()->for($order)->create([
            'product_name' => 'Gaming Laptop',
            'unit_price' => 54990.50,
            'quantity' => 2,
            'line_total' => 109981,
        ]);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001');

        $response->assertOk();
        $response->assertSeeInOrder(['Order ORD-10001', 'Placed on 9 Oct 2026, 10:30 AM']);
        $response->assertSeeInOrder(['Gaming Laptop', '54,990.50', '109,981.00', 'Total', '110,780.00']);
        $response->assertSeeInOrder(['Priya Sharma', '12 MG Road', 'Jaipur', 'Rajasthan', '302001', '9876543210', 'priya@example.com']);
        $response->assertSeeInOrder(['Cash on delivery', 'Pending', 'Placed']);
    }

    #[TestWith([OrderStatus::Placed], 'placed')]
    #[TestWith([OrderStatus::Confirmed], 'confirmed')]
    public function test_offers_to_cancel_an_order_that_has_not_been_prepared_yet(OrderStatus $status): void
    {
        $order = Order::factory()->status($status)->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001');

        $response->assertOk();
        $response->assertSee('Cancel order');
        $response->assertSee(route('orders.cancel', 'ORD-10001'));
    }

    #[TestWith([OrderStatus::Processing], 'processing')]
    #[TestWith([OrderStatus::Shipped], 'shipped')]
    #[TestWith([OrderStatus::Delivered], 'delivered')]
    #[TestWith([OrderStatus::Cancelled], 'cancelled')]
    public function test_does_not_offer_to_cancel_an_order_that_is_past_that_point(OrderStatus $status): void
    {
        $order = Order::factory()->status($status)->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001');

        $response->assertOk();
        $response->assertDontSee('Cancel order');
    }

    public function test_the_order_of_another_customer_is_not_found(): void
    {
        Order::factory()->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs(User::factory()->create())->get('/orders/ORD-10001');

        $response->assertNotFound();
    }

    public function test_an_unknown_order_number_is_not_found(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/orders/ORD-99999');

        $response->assertNotFound();
    }

    public function test_escapes_the_delivery_details_and_product_names(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-10001',
            'shipping_address' => [
                'name' => "<script>alert('name')</script>",
                'mobile' => '9876543210',
                'email' => 'priya@example.com',
                'address' => "<script>alert('address')</script>",
                'city' => "<script>alert('city')</script>",
                'state' => "<script>alert('state')</script>",
                'pincode' => '302001',
            ],
        ]);
        OrderItem::factory()->for($order)->create(['product_name' => "<script>alert('product')</script>"]);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(', false);
    }
}
