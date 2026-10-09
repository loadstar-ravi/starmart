<?php

namespace Tests\Unit\View\Components\Admin;

use App\View\Components\Admin\SalesChart;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SalesChartTest extends TestCase
{
    public function test_ends_the_axis_on_a_round_number_above_the_best_day(): void
    {
        $chart = new SalesChart($this->days([140, 1799, 0]));

        $this->assertSame(2000.0, $chart->axisMax);
        $this->assertSame(
            [['₹0', 0.0], ['₹500', 25.0], ['₹1,000', 50.0], ['₹1,500', 75.0], ['₹2,000', 100.0]],
            array_map(fn (array $tick) => [$tick['label'], $tick['position']], $chart->ticks),
        );
    }

    public function test_keeps_the_axis_at_the_best_day_when_that_is_already_a_round_number(): void
    {
        $chart = new SalesChart($this->days([2000]));

        $this->assertSame(2000.0, $chart->axisMax);
        $this->assertSame(100.0, $chart->columns[0]['height']);
    }

    public function test_writes_the_axis_with_decimals_when_its_steps_are_not_whole_rupees(): void
    {
        $chart = new SalesChart($this->days([9]));

        $this->assertSame(
            ['₹0.00', '₹2.50', '₹5.00', '₹7.50', '₹10.00'],
            array_column($chart->ticks, 'label'),
        );
    }

    public function test_makes_each_column_as_tall_as_its_share_of_the_axis(): void
    {
        $chart = new SalesChart($this->days([140, 1799, 0]));

        $this->assertSame([7.0, 89.95, 0.0], array_column($chart->columns, 'height'));
    }

    public function test_draws_nothing_when_no_day_had_sales(): void
    {
        $chart = new SalesChart($this->days([0, 0, 0]));

        $this->assertSame(0.0, $chart->axisMax);
        $this->assertSame([], $chart->ticks);
        $this->assertSame([], $chart->columns);
    }

    public function test_marks_only_the_first_of_the_best_days_as_the_peak(): void
    {
        $chart = new SalesChart($this->days([100, 300, 300]));

        $this->assertSame([false, true, false], array_column($chart->columns, 'isPeak'));
    }

    public function test_writes_at_most_seven_dates_counting_back_from_the_last_day(): void
    {
        $chart = new SalesChart($this->days(array_fill(0, 30, 100)));

        $this->assertSame(
            [4, 9, 14, 19, 24, 29],
            array_keys(array_filter(array_column($chart->columns, 'showsDate'))),
        );
    }

    public function test_keeps_every_other_date_for_narrow_screens_still_counting_back_from_the_last_day(): void
    {
        $chart = new SalesChart($this->days(array_fill(0, 30, 100)));

        $this->assertSame(
            [9, 19, 29],
            array_keys(array_filter(array_column($chart->columns, 'showsDateWhenNarrow'))),
        );
    }

    public function test_writes_every_date_when_there_are_seven_days_or_fewer(): void
    {
        $chart = new SalesChart($this->days(array_fill(0, 7, 100)));

        $this->assertSame(array_fill(0, 7, true), array_column($chart->columns, 'showsDate'));
    }

    public function test_opens_the_tooltips_near_an_edge_towards_the_middle(): void
    {
        $chart = new SalesChart($this->days(array_fill(0, 6, 100)));

        $this->assertSame(
            ['start', 'start', 'center', 'center', 'end', 'end'],
            array_column($chart->columns, 'side'),
        );
    }

    /**
     * Build one day per amount, starting on 1 October 2026.
     *
     * @param  list<float|int>  $sales
     * @return Collection<int, array{date: CarbonImmutable, orders: int, sales: string}>
     */
    private function days(array $sales): Collection
    {
        return collect($sales)->map(fn (float|int $amount, int $index) => [
            'date' => CarbonImmutable::parse('2026-10-01')->addDays($index),
            'orders' => $amount > 0 ? 1 : 0,
            'sales' => number_format($amount, 2, '.', ''),
        ]);
    }
}
