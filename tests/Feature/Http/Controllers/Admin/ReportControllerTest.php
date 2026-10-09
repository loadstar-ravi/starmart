<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_admin_login_form(): void
    {
        $response = $this->get('/admin/reports');

        $response->assertRedirectToRoute('admin.login');
    }

    public function test_forbids_customers(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/admin/reports');

        $response->assertForbidden();
    }

    public function test_admin_navigation_links_to_the_reports(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/orders');

        $response->assertOk();
        $response->assertSee(route('admin.reports.index'));
    }

    public function test_reports_the_last_thirty_days_when_no_range_is_given(): void
    {
        $this->travelTo('2026-10-09 12:00:00');

        $response = $this->actingAs($this->admin())->get('/admin/reports');

        $response->assertOk();
        $response->assertViewHas('from', fn (CarbonImmutable $from) => $from->toDateString() === '2026-09-10');
        $response->assertViewHas('to', fn (CarbonImmutable $to) => $to->toDateString() === '2026-10-09');
        $response->assertSeeInOrder(['name="from"', 'value="2026-09-10"', 'name="to"', 'value="2026-10-09"'], false);
        $response->assertSeeText('10 Sep 2026 to 9 Oct 2026');
    }

    public function test_marks_the_quick_range_the_report_is_showing(): void
    {
        $this->travelTo('2026-10-09 12:00:00');

        $response = $this->actingAs($this->admin())->get('/admin/reports?from=2026-10-03&to=2026-10-09');

        $response->assertOk();
        $response->assertSeeInOrder(['aria-current="true"', 'Last 7 days', 'Last 30 days', 'Last 90 days'], false);
        $response->assertSee('from=2026-07-12&amp;to=2026-10-09', false);
    }

    public function test_ends_today_when_only_a_start_date_is_given(): void
    {
        $this->travelTo('2026-10-09 12:00:00');

        $response = $this->actingAs($this->admin())->get('/admin/reports?from=2026-10-05');

        $response->assertOk();
        $response->assertSeeText('5 Oct 2026 to 9 Oct 2026');
    }

    public function test_reports_the_sales_orders_and_average_order_of_the_chosen_range(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        Order::factory()->paidOnline()->create(['total_amount' => 500, 'created_at' => '2026-10-01 10:00:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 250.50, 'created_at' => '2026-10-03 10:00:00']);
        Order::factory()->create(['total_amount' => 7777, 'created_at' => '2026-10-02 10:00:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 8888, 'created_at' => '2026-10-04 10:00:00']);

        $response = $this->actingAs($this->admin())->get('/admin/reports?from=2026-10-01&to=2026-10-03');

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Sales', '750.50',
            'Paid orders', '2',
            'Average order value', '375.25',
            'Sales per day', '1 Oct 2026 to 3 Oct 2026',
        ]);
        $response->assertSee('3 Oct 2026: ₹250.50 from 1 paid order');
        $response->assertDontSee('7,777');
        $response->assertDontSee('8,888');
    }

    public function test_lists_every_day_of_the_range_with_its_orders_and_sales(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        Order::factory()->paidOnline()->create(['total_amount' => 500, 'created_at' => '2026-10-01 10:00:00']);
        Order::factory()->paidOnline()->create(['total_amount' => 250.50, 'created_at' => '2026-10-03 10:00:00']);

        $response = $this->actingAs($this->admin())->get('/admin/reports?from=2026-10-01&to=2026-10-03');

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Daily figures',
            '1 Oct 2026', '1', '500.00',
            '2 Oct 2026', '0', '0.00',
            '3 Oct 2026', '1', '250.50',
            'Total', '2', '750.50',
        ]);
    }

    public function test_lists_the_best_selling_products_of_the_range(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        $order = Order::factory()->paidOnline()->create(['created_at' => '2026-10-02 10:00:00']);
        OrderItem::factory()->for($order)->create(['product_name' => 'Gaming Laptop', 'quantity' => 1, 'line_total' => 50000]);
        OrderItem::factory()->for($order)->create(['product_name' => 'Wireless Mouse', 'quantity' => 5, 'line_total' => 3995]);
        $earlier = Order::factory()->paidOnline()->create(['created_at' => '2026-09-30 10:00:00']);
        OrderItem::factory()->for($earlier)->create(['product_name' => 'Desk Lamp', 'quantity' => 9]);

        $response = $this->actingAs($this->admin())->get('/admin/reports?from=2026-10-01&to=2026-10-03');

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Best-selling products',
            'Wireless Mouse', '5', '3,995.00',
            'Gaming Laptop', '1', '50,000.00',
            'Daily figures',
        ]);
        $response->assertDontSee('Desk Lamp');
    }

    public function test_says_so_when_nothing_was_sold_in_the_range(): void
    {
        $this->travelTo('2026-10-09 12:00:00');

        $response = $this->actingAs($this->admin())->get('/admin/reports?from=2026-10-01&to=2026-10-03');

        $response->assertOk();
        $response->assertSee('No sales in this period.');
        $response->assertSee('No products were sold in this period.');
    }

    public function test_accepts_a_range_of_exactly_92_days(): void
    {
        $this->travelTo('2026-10-09 12:00:00');

        $response = $this->actingAs($this->admin())->get('/admin/reports?from=2026-07-10&to=2026-10-09');

        $response->assertOk();
        $response->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidRanges(): array
    {
        return [
            'start date that is not a date' => [
                'from=2026-13-45',
                'from',
                'The start date field must match the format Y-m-d.',
            ],
            'end date in another format' => [
                'to=09/10/2026',
                'to',
                'The end date field must match the format Y-m-d.',
            ],
            'start date after the end date' => [
                'from=2026-10-08&to=2026-10-01',
                'from',
                'The start date must not be after the end date.',
            ],
            'end date in the future' => [
                'from=2026-10-01&to=2026-10-10',
                'to',
                'The end date must not be in the future.',
            ],
            'range of 93 days' => [
                'from=2026-07-09&to=2026-10-09',
                'from',
                'The report can cover at most 92 days.',
            ],
        ];
    }

    #[DataProvider('invalidRanges')]
    public function test_rejects_an_invalid_range(string $query, string $field, string $message): void
    {
        $this->travelTo('2026-10-09 12:00:00');

        $response = $this->actingAs($this->admin())->get("/admin/reports?{$query}");

        $response->assertSessionHasErrors([$field => $message]);
    }

    public function test_escapes_product_names(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        $order = Order::factory()->paidOnline()->create(['created_at' => '2026-10-02 10:00:00']);
        OrderItem::factory()->for($order)->for(Product::factory())->create(['product_name' => "<script>alert('xss')</script>"]);

        $response = $this->actingAs($this->admin())->get('/admin/reports?from=2026-10-01&to=2026-10-03');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
