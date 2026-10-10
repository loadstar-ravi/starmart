<?php

namespace Tests\Feature\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Listeners\SendOrderStatusNotification;
use App\Models\Order;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class SendOrderStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_the_customer_the_order_belongs_to(): void
    {
        $order = Order::factory()->status(OrderStatus::Shipped)->create();
        Notification::fake();

        (new SendOrderStatusNotification)->handle(new OrderStatusChanged($order));

        Notification::assertSentTo(
            $order->user,
            fn (OrderStatusUpdated $notification) => $notification->order->is($order),
        );
    }

    public function test_logs_the_order_when_every_attempt_has_failed(): void
    {
        $order = Order::factory()->status(OrderStatus::Shipped)->create(['order_number' => 'ORD-10001']);
        Log::spy();

        (new SendOrderStatusNotification)->failed(
            new OrderStatusChanged($order),
            new RuntimeException('The mail server is down.'),
        );

        Log::shouldHaveReceived('error')->once()->with('Order status notification could not be sent.', [
            'order_id' => $order->id,
            'order_number' => 'ORD-10001',
            'status' => 'shipped',
            'exception' => 'The mail server is down.',
        ]);
    }
}
