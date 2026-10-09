<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sent on the queue so a slow or failing mail server never holds up the checkout.
 * A failed attempt is retried after 10 seconds, then after a minute.
 */
#[Tries(3)]
#[Backoff(10, 60)]
#[Timeout(30)]
class SendOrderConfirmationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Order $order) {}

    /**
     * Tell the customer their order has been received.
     */
    public function handle(): void
    {
        $this->order->loadMissing(['user', 'items']);

        $this->order->user->notify(new OrderConfirmed($this->order));
    }

    /**
     * Record that the customer never got the confirmation, once every attempt has failed.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Order confirmation could not be sent.', [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
