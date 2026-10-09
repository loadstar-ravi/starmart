<?php

namespace Tests\Feature\Notifications;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConfirmedTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_sent_by_mail_with_the_order_number_in_the_subject(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-10001']);
        $notification = new OrderConfirmed($order);

        $mail = $notification->toMail($order->user);

        $this->assertSame(['mail'], $notification->via($order->user));
        $this->assertSame('Order ORD-10001 Confirmed', $mail->subject);
    }

    public function test_mail_shows_the_lines_the_total_the_payment_method_and_a_link_to_the_order(): void
    {
        $order = Order::factory()
            ->for(User::factory()->create(['name' => 'Priya Sharma']))
            ->create(['order_number' => 'ORD-10001', 'total_amount' => 110780, 'payment_method' => PaymentMethod::Cod]);
        OrderItem::factory()->for($order)->create([
            'product_name' => 'Gaming Laptop',
            'quantity' => 2,
            'line_total' => 109981,
        ]);

        $html = (string) (new OrderConfirmed($order))->toMail($order->user)->render();

        $this->assertStringContainsString('Hello Priya Sharma,', $html);
        $this->assertStringContainsString('Gaming Laptop', $html);
        $this->assertStringContainsString('109,981.00', $html);
        $this->assertStringContainsString('110,780.00', $html);
        $this->assertStringContainsString('Cash on delivery', $html);
        $this->assertStringContainsString(route('orders.show', 'ORD-10001'), $html);
    }

    public function test_mail_escapes_the_customer_and_product_names(): void
    {
        $order = Order::factory()
            ->for(User::factory()->create(['name' => "<script>alert('customer')</script>"]))
            ->create();
        OrderItem::factory()->for($order)->create(['product_name' => "<script>alert('product')</script>"]);

        $html = (string) (new OrderConfirmed($order))->toMail($order->user)->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('customer')</script>", $html);
        $this->assertStringNotContainsString("<script>alert('product')</script>", $html);
    }
}
