<?php

namespace Tests\Feature\Services;

use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SalesReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_counts_customers_but_not_admins_products_and_orders_and_adds_up_the_paid_ones(): void
    {
        Product::factory()->for(Category::factory())->count(2)->create();
        User::factory()->admin()->create();
        User::factory()->create();
        $customer = User::factory()->create();
        Order::factory()->for($customer)->count(2)->create(['total_amount' => 9999]);
        Order::factory()->for($customer)->paidOnline()->create(['total_amount' => 500]);
        Order::factory()->for($customer)->paidOnline()->create(['total_amount' => 250.50]);
        Order::factory()->for($customer)->create(['payment_status' => PaymentStatus::Failed, 'total_amount' => 9999]);

        $overview = (new SalesReportService)->overview();

        $this->assertSame([
            'customers' => 2,
            'products' => 2,
            'orders' => 5,
            'orders_by_payment_status' => ['pending' => 2, 'success' => 2, 'failed' => 1, 'refunded' => 0],
            'sales' => '750.50',
        ], $overview);
    }

    public function test_overview_of_a_shop_without_anything_is_all_zeros(): void
    {
        $overview = (new SalesReportService)->overview();

        $this->assertSame([
            'customers' => 0,
            'products' => 0,
            'orders' => 0,
            'orders_by_payment_status' => ['pending' => 0, 'success' => 0, 'failed' => 0, 'refunded' => 0],
            'sales' => '0.00',
        ], $overview);
    }

    public function test_daily_sales_list_every_day_of_the_range_including_days_without_sales(): void
    {
        Order::factory()->paidOnline()->create(['total_amount' => 500, 'created_at' => '2026-10-07 10:00:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 250.50, 'created_at' => '2026-10-07 18:30:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 100, 'created_at' => '2026-10-09 09:00:00']);

        $days = (new SalesReportService)->dailySales($this->day('2026-10-07'), $this->day('2026-10-09'));

        $this->assertSame([
            ['2026-10-07', 2, '750.50'],
            ['2026-10-08', 0, '0.00'],
            ['2026-10-09', 1, '100.00'],
        ], $this->rows($days));
    }

    public function test_daily_sales_cover_the_first_and_last_day_in_full_and_nothing_outside_them(): void
    {
        Order::factory()->paidOnline()->create(['total_amount' => 9999, 'created_at' => '2026-10-06 23:59:59']);
        Order::factory()->paidOnline()->create(['total_amount' => 100, 'created_at' => '2026-10-07 00:00:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 200, 'created_at' => '2026-10-08 23:59:59']);
        Order::factory()->paidOnline()->create(['total_amount' => 9999, 'created_at' => '2026-10-09 00:00:00']);

        $days = (new SalesReportService)->dailySales($this->day('2026-10-07'), $this->day('2026-10-08'));

        $this->assertSame([
            ['2026-10-07', 1, '100.00'],
            ['2026-10-08', 1, '200.00'],
        ], $this->rows($days));
    }

    #[TestWith([PaymentStatus::Pending], 'not paid yet')]
    #[TestWith([PaymentStatus::Failed], 'payment failed')]
    #[TestWith([PaymentStatus::Refunded], 'refunded')]
    public function test_daily_sales_leave_out_orders_that_are_not_paid(PaymentStatus $paymentStatus): void
    {
        Order::factory()->create(['payment_status' => $paymentStatus, 'created_at' => '2026-10-07 10:00:00']);

        $days = (new SalesReportService)->dailySales($this->day('2026-10-07'), $this->day('2026-10-07'));

        $this->assertSame([['2026-10-07', 0, '0.00']], $this->rows($days));
    }

    public function test_totals_add_up_the_days_and_average_the_value_of_an_order(): void
    {
        Order::factory()->paidOnline()->create(['total_amount' => 500, 'created_at' => '2026-10-07 10:00:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 250.50, 'created_at' => '2026-10-08 10:00:00']);
        $service = new SalesReportService;

        $totals = $service->totals($service->dailySales($this->day('2026-10-07'), $this->day('2026-10-09')));

        $this->assertSame(['orders' => 2, 'sales' => '750.50', 'average_order' => '375.25'], $totals);
    }

    public function test_totals_of_days_without_sales_are_zero(): void
    {
        $service = new SalesReportService;

        $totals = $service->totals($service->dailySales($this->day('2026-10-07'), $this->day('2026-10-09')));

        $this->assertSame(['orders' => 0, 'sales' => '0.00', 'average_order' => '0.00'], $totals);
    }

    public function test_top_products_rank_products_by_the_units_sold_in_paid_orders_of_the_range(): void
    {
        $laptop = Product::factory()->create();
        $mouse = Product::factory()->create();
        $first = Order::factory()->paidOnline()->create(['created_at' => '2026-10-07 10:00:00']);
        $this->sell($first, $laptop, 'Gaming Laptop', 1, 50000);
        $this->sell($first, $mouse, 'Wireless Mouse', 2, 1598);
        $second = Order::factory()->paidOnline()->create(['created_at' => '2026-10-08 10:00:00']);
        $this->sell($second, $mouse, 'Wireless Mouse', 3, 2397);
        $unpaid = Order::factory()->create(['created_at' => '2026-10-08 10:00:00']);
        $this->sell($unpaid, $laptop, 'Gaming Laptop', 10, 500000);
        $earlier = Order::factory()->paidOnline()->create(['created_at' => '2026-10-06 10:00:00']);
        $this->sell($earlier, $laptop, 'Gaming Laptop', 20, 1000000);

        $products = (new SalesReportService)->topProducts($this->day('2026-10-07'), $this->day('2026-10-08'));

        $this->assertSame([
            ['name' => 'Wireless Mouse', 'units' => 5, 'sales' => '3995.00'],
            ['name' => 'Gaming Laptop', 'units' => 1, 'sales' => '50000.00'],
        ], $products->all());
    }

    public function test_top_products_rank_the_higher_sales_first_when_units_are_equal(): void
    {
        $order = Order::factory()->paidOnline()->create(['created_at' => '2026-10-07 10:00:00']);
        $this->sell($order, Product::factory()->create(), 'Wireless Mouse', 1, 799);
        $this->sell($order, Product::factory()->create(), 'Gaming Laptop', 1, 50000);

        $products = (new SalesReportService)->topProducts($this->day('2026-10-07'), $this->day('2026-10-07'));

        $this->assertSame(['Gaming Laptop', 'Wireless Mouse'], $products->pluck('name')->all());
    }

    public function test_top_products_still_list_a_product_that_was_deleted_since(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->paidOnline()->create(['created_at' => '2026-10-07 10:00:00']);
        $this->sell($order, $product, 'Discontinued Radio', 2, 1000);
        $product->delete();

        $products = (new SalesReportService)->topProducts($this->day('2026-10-07'), $this->day('2026-10-07'));

        $this->assertSame([['name' => 'Discontinued Radio', 'units' => 2, 'sales' => '1000.00']], $products->all());
    }

    public function test_top_products_stop_at_the_limit(): void
    {
        $order = Order::factory()->paidOnline()->create(['created_at' => '2026-10-07 10:00:00']);
        $this->sell($order, Product::factory()->create(), 'Gaming Laptop', 3, 150000);
        $this->sell($order, Product::factory()->create(), 'Wireless Mouse', 2, 1598);
        $this->sell($order, Product::factory()->create(), 'Desk Lamp', 1, 899);

        $products = (new SalesReportService)->topProducts($this->day('2026-10-07'), $this->day('2026-10-07'), 2);

        $this->assertSame(['Gaming Laptop', 'Wireless Mouse'], $products->pluck('name')->all());
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    /**
     * Reduce daily sales to rows of date, paid orders and sales, so they compare against plain values.
     *
     * @param  Collection<int, array{date: CarbonImmutable, orders: int, sales: string}>  $days
     * @return list<array{0: string, 1: int, 2: string}>
     */
    private function rows(Collection $days): array
    {
        return $days->map(fn (array $day) => [$day['date']->toDateString(), $day['orders'], $day['sales']])->all();
    }

    private function sell(Order $order, Product $product, string $name, int $quantity, float $lineTotal): void
    {
        OrderItem::factory()->for($order)->for($product)->create([
            'product_name' => $name,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
        ]);
    }
}
