<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_sales_and_the_number_of_orders_products_and_customers(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Product::factory()->for(Category::factory())->count(3)->create();
        Order::factory()->for($customer)->paidOnline()->create(['total_amount' => 500]);
        Order::factory()->for($customer)->paidOnline()->create(['total_amount' => 250.50]);
        Order::factory()->for($customer)->create(['total_amount' => 9999]);
        Order::factory()->for($customer)->create(['total_amount' => 9999]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Total sales', '750.50',
            'Orders', '4',
            'Products', '3',
            'Customers', '1',
            'Orders by payment status',
        ]);
    }

    public function test_links_the_customer_count_to_the_customers_in_the_user_list(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin');

        $response->assertOk();
        $response->assertSee(route('admin.users.index', ['role' => 'customer']));
    }

    public function test_counts_the_orders_by_payment_status_and_links_each_count_to_those_orders(): void
    {
        $customer = User::factory()->create();
        Order::factory()->for($customer)->count(2)->create();
        Order::factory()->for($customer)->paidOnline()->create();
        Order::factory()->for($customer)->count(3)->create(['payment_status' => PaymentStatus::Failed]);

        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin');

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Orders by payment status',
            'Pending', '2',
            'Paid', '1',
            'Failed', '3',
            'Refunded', '0',
            'Sales in the last 30 days',
        ]);
        $response->assertSee(route('admin.orders.index', ['payment_status' => 'failed']));
    }

    public function test_charts_the_paid_orders_of_the_last_thirty_days(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        Order::factory()->paidOnline()->create(['total_amount' => 1799, 'created_at' => '2026-10-09 09:00:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 500, 'created_at' => '2026-09-10 00:00:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 8888, 'created_at' => '2026-09-09 23:59:59']);
        Order::factory()->create(['total_amount' => 7777, 'created_at' => '2026-10-09 09:00:00']);

        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin');

        $response->assertOk();
        $response->assertSeeTextInOrder(['Sales in the last 30 days', '2,299.00', 'from 2 paid orders']);
        $response->assertSee('9 Oct 2026: ₹1,799.00 from 1 paid order');
        $response->assertSee('10 Sep 2026: ₹500.00 from 1 paid order');
        $response->assertSee('8 Oct 2026: ₹0.00 from 0 paid orders');
        $response->assertViewHas('dailySales', fn (Collection $days) => $days->count() === 30
            && $days->first()['date']->toDateString() === '2026-09-10'
            && $days->last()['date']->toDateString() === '2026-10-09');
    }

    public function test_says_so_when_nothing_was_sold_in_the_last_thirty_days(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        Order::factory()->paidOnline()->create(['created_at' => '2026-08-01 09:00:00']);

        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin');

        $response->assertOk();
        $response->assertSee('No sales in this period.');
        $response->assertSeeTextInOrder(['Sales in the last 30 days', '0.00', 'from 0 paid orders']);
    }

    public function test_offers_the_daily_figures_as_a_table_next_to_the_chart(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        Order::factory()->paidOnline()->create(['total_amount' => 1799, 'created_at' => '2026-10-09 09:00:00']);

        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin');

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Show the daily figures as a table',
            'Date', 'Paid orders', 'Sales',
            '10 Sep 2026', '0', '0.00',
            '9 Oct 2026', '1', '1,799.00',
            'Total', '1', '1,799.00',
        ]);
    }
}
