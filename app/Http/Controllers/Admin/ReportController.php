<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Services\SalesReportService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * The quick ranges offered above the report, as the number of days each one covers up to today.
     *
     * @var list<int>
     */
    private const array PRESET_DAYS = [7, 30, 90];

    /**
     * Show the sales report for a range of days: the last 30 unless another range is asked for.
     */
    public function index(SalesReportRequest $request, SalesReportService $reports): View
    {
        [$from, $to] = [$request->from(), $request->to()];

        $dailySales = $reports->dailySales($from, $to);

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'presets' => $this->presets($from, $to),
            'dailySales' => $dailySales,
            'totals' => $reports->totals($dailySales),
            'topProducts' => $reports->topProducts($from, $to),
        ]);
    }

    /**
     * Build the quick range links, marking the one the report is showing.
     *
     * @return list<array{label: string, url: string, active: bool}>
     */
    private function presets(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today = now()->toImmutable()->startOfDay();

        return array_map(function (int $days) use ($from, $to, $today) {
            $firstDay = $today->subDays($days - 1);

            return [
                'label' => "Last {$days} days",
                'url' => route('admin.reports.index', [
                    'from' => $firstDay->toDateString(),
                    'to' => $today->toDateString(),
                ]),
                'active' => $from->equalTo($firstDay) && $to->equalTo($today),
            ];
        }, self::PRESET_DAYS);
    }
}
