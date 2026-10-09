<?php

namespace App\Exceptions;

use App\Models\Order;
use Exception;

/**
 * Thrown when an order cannot be paid for. The message is written for the customer.
 */
class PaymentException extends Exception
{
    public function __construct(string $message, public readonly Order $order)
    {
        parent::__construct($message);
    }

    /**
     * The order is paid in cash when it is delivered, not online.
     */
    public static function cashOnDelivery(Order $order): self
    {
        return new self("Order {$order->order_number} is paid in cash on delivery.", $order);
    }

    /**
     * The order has been paid in full.
     */
    public static function alreadyPaid(Order $order): self
    {
        return new self("Order {$order->order_number} is already paid.", $order);
    }

    /**
     * The payment for the order was given back.
     */
    public static function refunded(Order $order): self
    {
        return new self("Order {$order->order_number} was refunded and cannot be paid again.", $order);
    }

    /**
     * The order was cancelled before it was paid.
     */
    public static function orderCancelled(Order $order): self
    {
        return new self("Order {$order->order_number} is cancelled and cannot be paid.", $order);
    }

    /**
     * The amount offered is not what the order costs.
     */
    public static function amountMismatch(Order $order): self
    {
        $total = number_format((float) $order->total_amount, 2);

        return new self("The amount does not match the order total of ₹{$total}.", $order);
    }
}
