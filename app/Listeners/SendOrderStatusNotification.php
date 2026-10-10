<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs on the queue, so a slow or failing mail server never holds up the admin or the customer
 * who changed the order. A failed attempt is retried after 10 seconds, then after a minute.
 */
#[Tries(3)]
#[Backoff(10, 60)]
#[Timeout(30)]
class SendOrderStatusNotification implements ShouldQueue
{
    /**
     * Tell the customer what happened to their order.
     */
    public function handle(OrderStatusChanged $event): void
    {
        $event->order->loadMissing('user');

        $event->order->user->notify(new OrderStatusUpdated($event->order));
    }

    /**
     * Record that the customer never heard about the change, once every attempt has failed.
     */
    public function failed(OrderStatusChanged $event, Throwable $exception): void
    {
        Log::error('Order status notification could not be sent.', [
            'order_id' => $event->order->id,
            'order_number' => $event->order->order_number,
            'status' => $event->order->status->value,
            'exception' => $exception->getMessage(),
        ]);
    }
}
