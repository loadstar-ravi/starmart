<?php

namespace App\Enums;

enum ProductSort: string
{
    case Newest = 'newest';
    case PriceLowToHigh = 'price_asc';
    case PriceHighToLow = 'price_desc';

    /**
     * Get the name shown to people for this sort order.
     */
    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Newest',
            self::PriceLowToHigh => 'Price: Low to High',
            self::PriceHighToLow => 'Price: High to Low',
        };
    }
}
