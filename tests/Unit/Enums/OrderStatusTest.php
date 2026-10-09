<?php

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param  list<OrderStatus>  $expected
     */
    #[TestWith([OrderStatus::Placed, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered]], 'placed')]
    #[TestWith([OrderStatus::Confirmed, [OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered]], 'confirmed')]
    #[TestWith([OrderStatus::Processing, [OrderStatus::Shipped, OrderStatus::Delivered]], 'processing')]
    #[TestWith([OrderStatus::Shipped, [OrderStatus::Delivered]], 'shipped')]
    #[TestWith([OrderStatus::Delivered, []], 'delivered')]
    #[TestWith([OrderStatus::Cancelled, []], 'cancelled')]
    public function test_later_steps_are_the_fulfilment_steps_still_ahead(OrderStatus $status, array $expected): void
    {
        $this->assertSame($expected, $status->laterSteps());
    }

    /**
     * Every pair of statuses, with whether an order may move from the first to the second.
     *
     * @return array<string, array{0: OrderStatus, 1: OrderStatus, 2: bool}>
     */
    public static function moves(): array
    {
        $allowed = [
            'placed' => ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
            'confirmed' => ['processing', 'shipped', 'delivered', 'cancelled'],
            'processing' => ['shipped', 'delivered'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];

        $moves = [];

        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $moves["{$from->value} to {$to->value}"] = [$from, $to, in_array($to->value, $allowed[$from->value], true)];
            }
        }

        return $moves;
    }

    #[DataProvider('moves')]
    public function test_an_order_moves_only_forward_or_to_cancelled_while_it_is_cancellable(OrderStatus $from, OrderStatus $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canMoveTo($to));
    }
}
