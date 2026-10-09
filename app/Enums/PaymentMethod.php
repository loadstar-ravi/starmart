<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Online = 'online';

    /**
     * Get the name shown to people for this payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Cash on delivery',
            self::Online => 'Online payment',
        };
    }
}
