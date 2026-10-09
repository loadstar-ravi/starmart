<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSuccessControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_login_form(): void
    {
        Order::factory()->create(['order_number' => 'ORD-10001']);

        $response = $this->get('/orders/ORD-10001/success');

        $response->assertRedirectToRoute('login');
    }

    public function test_shows_the_customer_the_order_they_placed(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-10001',
            'total_amount' => 110780,
            'payment_method' => PaymentMethod::Cod,
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

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001/success');

        $response->assertOk();
        $response->assertSeeInOrder(['Your order is placed', 'ORD-10001']);
        $response->assertSeeInOrder(['Gaming Laptop', '54,990.50', '109,981.00', 'Total', '110,780.00']);
        $response->assertSeeInOrder(['Priya Sharma', '12 MG Road', 'Jaipur', 'Rajasthan', '302001', '9876543210', 'priya@example.com']);
        $response->assertSeeInOrder(['Cash on delivery', 'Pending', 'Placed']);
    }

    public function test_offers_to_pay_for_an_online_order_that_is_not_paid_yet(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-10001', 'payment_method' => PaymentMethod::Online]);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001/success');

        $response->assertOk();
        $response->assertSee('Payment needed');
        $response->assertSee(route('payments.create', 'ORD-10001'));
    }

    public function test_does_not_offer_to_pay_for_an_order_that_is_paid(): void
    {
        $order = Order::factory()->paidOnline()->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001/success');

        $response->assertOk();
        $response->assertSee('Paid');
        $response->assertDontSee('Payment needed');
        $response->assertDontSee(route('payments.create', 'ORD-10001'));
    }

    public function test_does_not_offer_to_pay_online_for_a_cash_on_delivery_order(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-10001', 'payment_method' => PaymentMethod::Cod]);

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001/success');

        $response->assertOk();
        $response->assertDontSee('Payment needed');
    }

    public function test_the_order_of_another_customer_is_not_found(): void
    {
        Order::factory()->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs(User::factory()->create())->get('/orders/ORD-10001/success');

        $response->assertNotFound();
    }

    public function test_an_unknown_order_number_is_not_found(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/orders/ORD-99999/success');

        $response->assertNotFound();
    }

    public function test_forbids_admins(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->for($admin)->create(['order_number' => 'ORD-10001']);

        $response = $this->actingAs($admin)->get('/orders/ORD-10001/success');

        $response->assertForbidden();
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

        $response = $this->actingAs($order->user)->get('/orders/ORD-10001/success');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(', false);
    }
}
