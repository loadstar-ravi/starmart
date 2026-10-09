<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendOrderConfirmationJob;
use App\Models\Order;
use App\Notifications\OrderConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class SendOrderConfirmationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_the_customer_who_placed_the_order(): void
    {
        $order = Order::factory()->create();
        Notification::fake();

        (new SendOrderConfirmationJob($order))->handle();

        Notification::assertSentTo(
            $order->user,
            fn (OrderConfirmed $notification) => $notification->order->is($order),
        );
    }

    public function test_logs_the_order_when_every_attempt_has_failed(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-10001']);
        Log::spy();

        (new SendOrderConfirmationJob($order))->failed(new RuntimeException('The mail server is down.'));

        Log::shouldHaveReceived('error')->once()->with('Order confirmation could not be sent.', [
            'order_id' => $order->id,
            'order_number' => 'ORD-10001',
            'exception' => 'The mail server is down.',
        ]);
    }
}
