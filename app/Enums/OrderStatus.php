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
     * Get the name shown to people for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Placed',
            self::Confirmed => 'Confirmed',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Determine whether a customer may still cancel an order in this status.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Placed, self::Confirmed], true);
    }
}
