<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Jobs\SendOrderConfirmationJob;

class SendOrderConfirmation
{
    /**
     * Queue the confirmation email for the order that was placed.
     *
     * The listener itself is quick; the job does the sending in the background and retries when it fails.
     */
    public function handle(OrderPlaced $event): void
    {
        SendOrderConfirmationJob::dispatch($event->order);
    }
}
