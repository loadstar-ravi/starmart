<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Determine whether a customer may still cancel an order in this status.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Placed, self::Confirmed], true);
    }
}
