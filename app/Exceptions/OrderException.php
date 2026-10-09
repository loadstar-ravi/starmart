<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Models\Order;
use Exception;
use Illuminate\Support\Str;

/**
 * Thrown when a change to an order breaks a rule of the order.
 * The message is written for the person making the change.
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

    /**
     * The order cannot be moved to the given status: it is there already,
     * it is finished, or the status lies behind it.
     */
    public static function cannotBeMovedTo(Order $order, OrderStatus $status): self
    {
        $current = Str::lower($order->status->label());

        if ($order->status === $status) {
            return new self("Order {$order->order_number} is already {$current}.", $order);
        }

        if ($order->status->laterSteps() === []) {
            return new self("Order {$order->order_number} is {$current} and its status can no longer be changed.", $order);
        }

        $requested = Str::lower($status->label());

        return new self("Order {$order->order_number} is {$current} and cannot go back to {$requested}.", $order);
    }
}
