<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Models\Order;
use Exception;
use Illuminate\Support\Str;

/**
 * Thrown when a change to an order breaks a rule of the order. The message is written for the customer.
 */
class OrderException extends Exception
{
    public function __construct(string $message, public readonly Order $order)
    {
        parent::__construct($message);
    }

    /**
     * The order is past the point where it can be cancelled, or is cancelled already.
     */
    public static function cannotBeCancelled(Order $order): self
    {
        if ($order->status === OrderStatus::Cancelled) {
            return new self("Order {$order->order_number} is already cancelled.", $order);
        }

        $status = Str::lower($order->status->label());

        return new self("Order {$order->order_number} is {$status} and can no longer be cancelled.", $order);
    }
}
