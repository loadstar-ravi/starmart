<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Works out the shop's figures for the admin dashboard and the sales report.
 *
 * A sale is an order that is paid, and it counts on the day the order was placed.
 * Orders that are unpaid, failed or refunded are not sales.
 */
class SalesReportService
{
    /**
     * How many days a report covers when no range is asked for.
     */
    public const int DEFAULT_DAYS = 30;

    /**
     * The longest range a report may cover, so the daily chart and table stay readable.
     */
    public const int MAX_DAYS = 92;

    public const int TOP_PRODUCTS = 10;

    /**
     * Get the all-time totals of the shop. Admins are not counted among the customers.
     *
     * @return array{customers: int, products: int, orders: int, orders_by_payment_status: array<string, int>, sales: string}
     */
    public function overview(): array
    {
        $byPaymentStatus = Order::query()
            ->toBase()
            ->select('payment_status')
            ->selectRaw('count(*) as orders_count')
            ->selectRaw('sum(total_amount) as amount')
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status');

        return [
            'customers' => User::query()->where('role', UserRole::Customer)->count(),
            'products' => Product::query()->count(),
            'orders' => (int) $byPaymentStatus->sum('orders_count'),
            'orders_by_payment_status' => collect(PaymentStatus::cases())
                ->mapWithKeys(fn (PaymentStatus $status) => [
                    $status->value => (int) ($byPaymentStatus->get($status->value)?->orders_count ?? 0),
                ])
                ->all(),
            'sales' => $this->money($byPaymentStatus->get(PaymentStatus::Success->value)?->amount ?? 0),
        ];
    }

    /**
     * Get the sales of every day from the first to the last given day, including days without any.
     *
     * @return Collection<int, array{date: CarbonImmutable, orders: int, sales: string}>
     */
    public function dailySales(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $sold = Order::query()
            ->paid()
            ->placedBetween($from, $to)
            ->toBase()
            ->selectRaw('date(created_at) as placed_on')
            ->selectRaw('count(*) as orders_count')
            ->selectRaw('sum(total_amount) as amount')
            ->groupBy('placed_on')
            ->get()
            ->keyBy('placed_on');

        $days = collect();

        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to->startOfDay()); $day = $day->addDay()) {
            $row = $sold->get($day->toDateString());

            $days->push([
                'date' => $day,
                'orders' => (int) ($row?->orders_count ?? 0),
                'sales' => $this->money($row?->amount ?? 0),
            ]);
        }

        return $days;
    }

    /**
     * Add up a run of daily sales.
     *
     * @param  Collection<int, array{date: CarbonImmutable, orders: int, sales: string}>  $dailySales
     * @return array{orders: int, sales: string, average_order: string}
     */
    public function totals(Collection $dailySales): array
    {
        $orders = (int) $dailySales->sum('orders');
        $sales = (float) $dailySales->sum(fn (array $day) => (float) $day['sales']);

        return [
            'orders' => $orders,
            'sales' => $this->money($sales),
            'average_order' => $this->money($orders > 0 ? $sales / $orders : 0),
        ];
    }

    /**
     * Get the products that sold the most units in the given days, best seller first.
     *
     * Lines are grouped by the name the product had when it was bought,
     * so a product that was deleted since still shows up.
     *
     * @return Collection<int, array{name: string, units: int, sales: string}>
     */
    public function topProducts(CarbonImmutable $from, CarbonImmutable $to, int $limit = self::TOP_PRODUCTS): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn (Builder $order) => $order->paid()->placedBetween($from, $to))
            ->toBase()
            ->select('product_id', 'product_name')
            ->selectRaw('sum(quantity) as units')
            ->selectRaw('sum(line_total) as amount')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('units')
            ->orderByDesc('amount')
            ->orderBy('product_name')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => [
                'name' => $row->product_name,
                'units' => (int) $row->units,
                'sales' => $this->money($row->amount),
            ]);
    }

    /**
     * Format an amount like a decimal column, whatever type the database returned it as.
     */
    private function money(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
