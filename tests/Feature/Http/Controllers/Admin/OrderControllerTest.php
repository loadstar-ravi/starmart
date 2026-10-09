<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_admin_login_form(): void
    {
        $order = Order::factory()->create();

        $this->get('/admin/orders')->assertRedirectToRoute('admin.login');
        $this->get("/admin/orders/{$order->id}")->assertRedirectToRoute('admin.login');
    }

    public function test_forbids_customers_even_from_their_own_order(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($order->user)->get('/admin/orders')->assertForbidden();
        $this->actingAs($order->user)->get("/admin/orders/{$order->id}")->assertForbidden();
    }

    public function test_admin_navigation_links_to_the_orders(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertSee(route('admin.orders.index'));
    }

    public function test_lists_the_orders_of_every_customer_newest_first_with_amounts_and_statuses(): void
    {
        Order::factory()
            ->for(User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']))
            ->create([
                'order_number' => 'ORD-10001',
                'total_amount' => 799,
                'payment_method' => PaymentMethod::Cod,
                'created_at' => '2026-10-07 09:00:00',
            ]);
        Order::factory()
            ->for(User::factory()->create(['name' => 'Arjun Mehta', 'email' => 'arjun@example.com']))
            ->paidOnline()
            ->status(OrderStatus::Shipped)
            ->create([
                'order_number' => 'ORD-10002',
                'total_amount' => 1250,
                'created_at' => '2026-10-09 14:05:00',
            ]);

        $response = $this->actingAs($this->admin())->get('/admin/orders');

        $response->assertOk();
        $response->assertSeeInOrder([
            'ORD-10002', 'Arjun Mehta', 'arjun@example.com', '9 Oct 2026, 2:05 PM', '1,250.00', 'Paid', 'Online payment', 'Shipped',
            'ORD-10001', 'Priya Sharma', 'priya@example.com', '7 Oct 2026, 9:00 AM', '799.00', 'Pending', 'Cash on delivery', 'Placed',
        ]);
    }

    public function test_shows_fifteen_orders_per_page(): void
    {
        Order::factory()->count(16)->sequence(fn ($sequence) => [
            'order_number' => sprintf('ORD-%05d', 20001 + $sequence->index),
            'created_at' => now()->subDays(16 - $sequence->index),
        ])->create();

        $firstPage = $this->actingAs($this->admin())->get('/admin/orders');
        $secondPage = $this->actingAs($this->admin())->get('/admin/orders?page=2');

        $firstPage->assertSee('ORD-20016');
        $firstPage->assertSee('ORD-20002');
        $firstPage->assertDontSee('ORD-20001');
        $secondPage->assertSee('ORD-20001');
        $secondPage->assertDontSee('ORD-20002');
    }

    #[TestWith(['10002'], 'part of the order number')]
    #[TestWith(['mehta'], 'part of the customer name')]
    #[TestWith(['arjun@example'], 'part of the customer email')]
    public function test_search_lists_only_the_orders_that_match(string $term): void
    {
        Order::factory()
            ->for(User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']))
            ->create(['order_number' => 'ORD-10001']);
        Order::factory()
            ->for(User::factory()->create(['name' => 'Arjun Mehta', 'email' => 'arjun@example.com']))
            ->create(['order_number' => 'ORD-10002']);

        $response = $this->actingAs($this->admin())->get('/admin/orders?search='.urlencode($term));

        $response->assertOk();
        $response->assertSee('ORD-10002');
        $response->assertDontSee('ORD-10001');
    }

    public function test_status_filter_lists_only_orders_in_that_status(): void
    {
        Order::factory()->status(OrderStatus::Shipped)->create(['order_number' => 'ORD-10001']);
        Order::factory()->create(['order_number' => 'ORD-10002']);

        $response = $this->actingAs($this->admin())->get('/admin/orders?status=shipped');

        $response->assertOk();
        $response->assertSee('ORD-10001');
        $response->assertDontSee('ORD-10002');
    }

    public function test_payment_status_filter_lists_only_orders_with_that_payment_status(): void
    {
        Order::factory()->paidOnline()->create(['order_number' => 'ORD-10001']);
        Order::factory()->create(['order_number' => 'ORD-10002']);

        $response = $this->actingAs($this->admin())->get('/admin/orders?payment_status=success');

        $response->assertOk();
        $response->assertSee('ORD-10001');
        $response->assertDontSee('ORD-10002');
    }

    public function test_search_and_filters_narrow_the_list_together(): void
    {
        $priya = User::factory()->create(['name' => 'Priya Sharma']);
        Order::factory()->for($priya)->status(OrderStatus::Shipped)->create(['order_number' => 'ORD-10001']);
        Order::factory()->for($priya)->create(['order_number' => 'ORD-10002']);
        Order::factory()->status(OrderStatus::Shipped)->create(['order_number' => 'ORD-10003']);

        $response = $this->actingAs($this->admin())->get('/admin/orders?search=priya&status=shipped');

        $response->assertOk();
        $response->assertSee('ORD-10001');
        $response->assertDontSee('ORD-10002');
        $response->assertDontSee('ORD-10003');
    }

    #[TestWith(['status=archived', 'status', 'The selected status is invalid.'], 'unknown order status')]
    #[TestWith(['payment_status=paid', 'payment_status', 'The selected payment status is invalid.'], 'unknown payment status')]
    public function test_rejects_a_filter_that_is_not_a_known_status(string $query, string $field, string $message): void
    {
        $response = $this->actingAs($this->admin())->get("/admin/orders?{$query}");

        $response->assertSessionHasErrors([$field => $message]);
    }

    public function test_escapes_customer_names_in_the_list(): void
    {
        Order::factory()->for(User::factory()->create(['name' => "<script>alert('xss')</script>"]))->create();

        $response = $this->actingAs($this->admin())->get('/admin/orders');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_shows_an_order_with_its_items_payment_attempts_customer_and_address(): void
    {
        $order = Order::factory()
            ->for(User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']))
            ->paidOnline()
            ->create([
                'order_number' => 'ORD-10001',
                'total_amount' => 110780,
                'created_at' => '2026-10-09 10:30:00',
                'shipping_address' => [
                    'name' => 'Ravi Sharma',
                    'mobile' => '9876543210',
                    'email' => 'ravi@example.com',
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
        Payment::factory()->for($order)->failed()->create(['amount' => 110780]);
        Payment::factory()->for($order)->successful()->create(['amount' => 110780, 'transaction_reference' => 'TXN-ABC123']);

        $response = $this->actingAs($this->admin())->get("/admin/orders/{$order->id}");

        $response->assertOk();
        $response->assertSeeInOrder(['Order ORD-10001', 'Placed on 9 Oct 2026, 10:30 AM']);
        $response->assertSeeInOrder(['Gaming Laptop', '54,990.50', '2', '109,981.00', 'Order total', '110,780.00']);
        $response->assertSeeInOrder([
            'Payment attempts', 'Online payment',
            '110,780.00', 'Failed', 'Payment declined by the bank.',
            '110,780.00', 'Paid', 'Reference TXN-ABC123',
        ]);
        $response->assertSeeInOrder(['Customer', 'Priya Sharma', 'priya@example.com']);
        $response->assertSeeInOrder(['Delivery to', 'Ravi Sharma', '12 MG Road', 'Jaipur', 'Rajasthan', '302001', '9876543210', 'ravi@example.com']);
    }

    public function test_offers_every_later_step_and_cancelling_for_an_order_that_is_confirmed(): void
    {
        $order = Order::factory()->status(OrderStatus::Confirmed)->create();

        $response = $this->actingAs($this->admin())->get("/admin/orders/{$order->id}");

        $response->assertOk();
        $response->assertSeeInOrder([
            'Move order to',
            '<option value="processing">Processing</option>',
            '<option value="shipped">Shipped</option>',
            '<option value="delivered">Delivered</option>',
        ], false);
        $response->assertDontSee('<option value="placed">', false);
        $response->assertDontSee('<option value="confirmed">', false);
        $response->assertSee('Cancel order');
    }

    public function test_offers_only_delivery_and_no_cancelling_for_an_order_that_is_shipped(): void
    {
        $order = Order::factory()->status(OrderStatus::Shipped)->create();

        $response = $this->actingAs($this->admin())->get("/admin/orders/{$order->id}");

        $response->assertOk();
        $response->assertSee('<option value="delivered">Delivered</option>', false);
        $response->assertDontSee('<option value="processing">', false);
        $response->assertDontSee('Cancel order');
        $response->assertSee('This order is past the point where it can be cancelled.');
    }

    #[TestWith([OrderStatus::Delivered, 'This order is delivered. Its status can no longer be changed.'], 'delivered')]
    #[TestWith([OrderStatus::Cancelled, 'This order is cancelled. Its status can no longer be changed.'], 'cancelled')]
    public function test_offers_no_status_change_for_an_order_that_is_finished(OrderStatus $status, string $message): void
    {
        $order = Order::factory()->status($status)->create();

        $response = $this->actingAs($this->admin())->get("/admin/orders/{$order->id}");

        $response->assertOk();
        $response->assertSee($message);
        $response->assertDontSee('Move order to');
        $response->assertDontSee('Cancel order');
    }

    public function test_an_unknown_order_is_not_found(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/orders/999999');

        $response->assertNotFound();
    }

    public function test_escapes_everything_the_customer_typed_on_the_order_page(): void
    {
        $order = Order::factory()
            ->for(User::factory()->create(['name' => "<script>alert('customer')</script>"]))
            ->create([
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

        $response = $this->actingAs($this->admin())->get("/admin/orders/{$order->id}");

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(', false);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
