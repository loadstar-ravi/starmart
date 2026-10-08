<?php

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    #[TestWith([OrderStatus::Placed, true], 'placed')]
    #[TestWith([OrderStatus::Confirmed, true], 'confirmed')]
    #[TestWith([OrderStatus::Processing, false], 'processing')]
    #[TestWith([OrderStatus::Shipped, false], 'shipped')]
    #[TestWith([OrderStatus::Delivered, false], 'delivered')]
    #[TestWith([OrderStatus::Cancelled, false], 'cancelled')]
    public function test_only_placed_and_confirmed_orders_are_cancellable(OrderStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isCancellable());
    }
}
