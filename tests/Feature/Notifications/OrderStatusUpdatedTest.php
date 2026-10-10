<?php

namespace Tests\Feature\Notifications;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OrderStatusUpdatedTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([OrderStatus::Confirmed, 'Your order ORD-10001 has been confirmed'], 'confirmed')]
    #[TestWith([OrderStatus::Processing, 'Your order ORD-10001 is being prepared'], 'processing')]
    #[TestWith([OrderStatus::Shipped, 'Your order ORD-10001 has been shipped'], 'shipped')]
    #[TestWith([OrderStatus::Delivered, 'Your order ORD-10001 has been delivered'], 'delivered')]
    #[TestWith([OrderStatus::Cancelled, 'Your order ORD-10001 has been cancelled'], 'cancelled')]
    public function test_is_sent_by_mail_and_says_what_happened_in_the_subject_and_the_first_line(OrderStatus $status, string $headline): void
    {
        $order = Order::factory()->status($status)->create(['order_number' => 'ORD-10001']);
        $notification = new OrderStatusUpdated($order);

        $mail = $notification->toMail($order->user);

        $this->assertSame(['mail'], $notification->via($order->user));
        $this->assertSame($headline, $mail->subject);
        $this->assertSame([$headline.'.'], $mail->introLines);
    }

    public function test_mail_greets_the_customer_and_links_to_their_order(): void
    {
        $order = Order::factory()
            ->for(User::factory()->create(['name' => 'Priya Sharma']))
            ->status(OrderStatus::Shipped)
            ->create(['order_number' => 'ORD-10001']);

        $html = (string) (new OrderStatusUpdated($order))->toMail($order->user)->render();

        $this->assertStringContainsString('Hello Priya Sharma,', $html);
        $this->assertStringContainsString('Your order ORD-10001 has been shipped.', $html);
        $this->assertStringContainsString(route('orders.show', 'ORD-10001'), $html);
    }

    public function test_mail_tells_the_customer_about_the_refund_when_a_paid_order_was_cancelled(): void
    {
        $order = Order::factory()->status(OrderStatus::Cancelled)->create([
            'total_amount' => 1799,
            'payment_status' => PaymentStatus::Refunded,
        ]);

        $mail = (new OrderStatusUpdated($order))->toMail($order->user);

        $this->assertContains('Your payment of ₹1,799.00 has been refunded.', $mail->introLines);
    }

    public function test_mail_mentions_no_refund_when_nothing_was_paid(): void
    {
        $order = Order::factory()->status(OrderStatus::Cancelled)->create(['payment_status' => PaymentStatus::Failed]);

        $mail = (new OrderStatusUpdated($order))->toMail($order->user);

        $this->assertCount(1, $mail->introLines);
    }

    public function test_mail_escapes_the_customer_name(): void
    {
        $order = Order::factory()
            ->for(User::factory()->create(['name' => "<script>alert('customer')</script>"]))
            ->status(OrderStatus::Shipped)
            ->create();

        $html = (string) (new OrderStatusUpdated($order))->toMail($order->user)->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('customer')</script>", $html);
    }
}
