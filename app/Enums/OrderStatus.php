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
     * The steps an order goes through on its way to the customer, in order.
     *
     * @var list<self>
     */
    private const array FULFILMENT_STEPS = [
        self::Placed,
        self::Confirmed,
        self::Processing,
        self::Shipped,
        self::Delivered,
    ];

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
     * Determine whether an order in this status may still be cancelled.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Placed, self::Confirmed], true);
    }

    /**
     * Get the fulfilment steps that still lie ahead of this status.
     * Delivered and cancelled orders have none.
     *
     * @return list<self>
     */
    public function laterSteps(): array
    {
        $position = array_search($this, self::FULFILMENT_STEPS, true);

        return $position === false ? [] : array_slice(self::FULFILMENT_STEPS, $position + 1);
    }

    /**
     * Determine whether an order in this status may be moved to the given one: forward to
     * any later step, or to cancelled while that is still allowed. An order never goes back.
     */
    public function canMoveTo(self $status): bool
    {
        return $status === self::Cancelled
            ? $this->isCancellable()
            : in_array($status, $this->laterSteps(), true);
    }
}
