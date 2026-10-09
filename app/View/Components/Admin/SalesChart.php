<?php

namespace App\View\Components\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * A column chart of sales per day.
 *
 * It works out the value axis and where every column, date and tooltip goes,
 * so the template only has to draw them.
 */
class SalesChart extends Component
{
    /**
     * How many equal steps the value axis is divided into.
     */
    private const int AXIS_STEPS = 4;

    /**
     * The most dates written under the columns, so they never run into each other.
     * On a narrow screen only every other one of them is shown.
     */
    private const int MAX_DATE_LABELS = 7;

    /**
     * The value at the top of the axis. Zero when no day had any sales, and then nothing is drawn.
     */
    public float $axisMax;

    /**
     * The lines across the chart, from the baseline up, with how far up each one sits in percent.
     *
     * @var list<array{label: string, position: float}>
     */
    public array $ticks = [];

    /**
     * One column per day, in date order.
     *
     * @var list<array{date: CarbonImmutable, orders: int, sales: string, height: float, center: float, isPeak: bool, showsDate: bool, showsDateWhenNarrow: bool, side: string}>
     */
    public array $columns = [];

    /**
     * Create a new component instance.
     *
     * @param  Collection<int, array{date: CarbonImmutable, orders: int, sales: string}>  $days
     */
    public function __construct(Collection $days, public string $label = 'Sales per day')
    {
        $days = $days->values();
        $highest = (float) $days->max(fn (array $day) => (float) $day['sales']);
        $step = $highest > 0 ? $this->roundStep($highest / self::AXIS_STEPS) : 0.0;

        $this->axisMax = $step * self::AXIS_STEPS;

        if ($this->axisMax <= 0) {
            return;
        }

        for ($index = 0; $index <= self::AXIS_STEPS; $index++) {
            $this->ticks[] = [
                'label' => '₹'.number_format($step * $index, fmod($step, 1.0) === 0.0 ? 0 : 2),
                'position' => (float) ($index / self::AXIS_STEPS * 100),
            ];
        }

        $count = $days->count();
        $dateEvery = max(1, (int) ceil($count / self::MAX_DATE_LABELS));
        $peak = $days->search(fn (array $day) => (float) $day['sales'] === $highest);

        foreach ($days as $index => $day) {
            $this->columns[] = [
                ...$day,
                'height' => round((float) $day['sales'] / $this->axisMax * 100, 2),
                'center' => round(($index + 0.5) / $count * 100, 2),
                'isPeak' => $index === $peak,
                'showsDate' => ($count - 1 - $index) % $dateEvery === 0,
                'showsDateWhenNarrow' => ($count - 1 - $index) % ($dateEvery * 2) === 0,
                'side' => match (true) {
                    $index < $count / 3 => 'start',
                    $index >= $count * 2 / 3 => 'end',
                    default => 'center',
                },
            ];
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.admin.sales-chart');
    }

    /**
     * Round a step up to the next 1, 2, 2.5 or 5 times a power of ten, so the axis reads in round numbers.
     */
    private function roundStep(float $step): float
    {
        $magnitude = 10 ** floor(log10($step));
        $fraction = $step / $magnitude;

        return $magnitude * match (true) {
            $fraction <= 1 => 1,
            $fraction <= 2 => 2,
            $fraction <= 2.5 => 2.5,
            $fraction <= 5 => 5,
            default => 10,
        };
    }
}
