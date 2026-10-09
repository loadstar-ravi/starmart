<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard: the shop's totals and the sales of the last 30 days.
     */
    public function __invoke(SalesReportService $reports): View
    {
        $today = now()->toImmutable()->startOfDay();

        $dailySales = $reports->dailySales($today->subDays(SalesReportService::DEFAULT_DAYS - 1), $today);

        return view('admin.dashboard', [
            'overview' => $reports->overview(),
            'paymentStatuses' => PaymentStatus::cases(),
            'dailySales' => $dailySales,
            'recentTotals' => $reports->totals($dailySales),
        ]);
    }
}
