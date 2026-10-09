<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_admin_login_form(): void
    {
        $customer = User::factory()->create();

        $this->get('/admin/users')->assertRedirectToRoute('admin.login');
        $this->get("/admin/users/{$customer->id}")->assertRedirectToRoute('admin.login');
    }

    public function test_forbids_customers_even_from_their_own_page(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin/users')->assertForbidden();
        $this->actingAs($customer)->get("/admin/users/{$customer->id}")->assertForbidden();
    }

    public function test_admin_navigation_links_to_the_users(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/orders');

        $response->assertOk();
        $response->assertSee(route('admin.users.index'));
    }

    public function test_lists_the_users_newest_first_with_their_role_orders_and_status(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'created_at' => '2026-10-01 09:00:00',
        ]);
        $priya = User::factory()->create([
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
            'created_at' => '2026-10-05 09:00:00',
        ]);
        Order::factory()->for($priya)->count(2)->create();
        User::factory()->blocked()->create([
            'name' => 'Arjun Mehta',
            'email' => 'arjun@example.com',
            'created_at' => '2026-10-08 09:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Registered on',
            'Arjun Mehta', 'arjun@example.com', 'Customer', '8 Oct 2026', '0', 'Blocked',
            'Priya Sharma', 'priya@example.com', 'Customer', '5 Oct 2026', '2', 'Active',
            'Asha Verma', 'asha@example.com', 'Admin', '1 Oct 2026', '0', 'Active',
        ]);
    }

    public function test_shows_fifteen_users_per_page(): void
    {
        User::factory()->count(16)->sequence(fn ($sequence) => [
            'name' => sprintf('Customer %02d', $sequence->index + 1),
            'created_at' => now()->subDays(20 - $sequence->index),
        ])->create();
        $admin = User::factory()->admin()->create(['created_at' => now()->subDays(30)]);

        $firstPage = $this->actingAs($admin)->get('/admin/users');
        $secondPage = $this->actingAs($admin)->get('/admin/users?page=2');

        $firstPage->assertSee('Customer 16');
        $firstPage->assertSee('Customer 02');
        $firstPage->assertDontSee('Customer 01');
        $secondPage->assertSee('Customer 01');
        $secondPage->assertDontSee('Customer 02');
    }

    #[TestWith(['mehta'], 'part of the name')]
    #[TestWith(['arjun@example'], 'part of the email')]
    public function test_search_lists_only_the_users_that_match(string $term): void
    {
        User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);
        User::factory()->create(['name' => 'Arjun Mehta', 'email' => 'arjun@example.com']);

        $response = $this->actingAs($this->admin())->get('/admin/users?search='.urlencode($term));

        $response->assertOk();
        $response->assertSee('Arjun Mehta');
        $response->assertDontSee('Priya Sharma');
    }

    public function test_role_filter_lists_only_users_with_that_role(): void
    {
        User::factory()->create(['name' => 'Priya Sharma']);
        $admin = User::factory()->admin()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com']);

        $response = $this->actingAs($admin)->get('/admin/users?role=admin');

        $response->assertOk();
        $response->assertSee('asha@example.com');
        $response->assertDontSee('Priya Sharma');
    }

    public function test_search_and_the_role_filter_narrow_the_list_together(): void
    {
        User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);
        User::factory()->admin()->create(['name' => 'Priya Nair', 'email' => 'nair@example.com']);
        User::factory()->create(['name' => 'Arjun Mehta', 'email' => 'arjun@example.com']);

        $response = $this->actingAs($this->admin())->get('/admin/users?search=priya&role=customer');

        $response->assertOk();
        $response->assertSee('priya@example.com');
        $response->assertDontSee('nair@example.com');
        $response->assertDontSee('arjun@example.com');
    }

    public function test_rejects_a_role_filter_that_is_not_a_role(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/users?role=manager');

        $response->assertSessionHasErrors(['role' => 'The selected role is invalid.']);
    }

    public function test_offers_to_block_active_customers_to_unblock_blocked_ones_and_neither_for_admins(): void
    {
        User::factory()->create(['name' => 'Priya Sharma']);
        User::factory()->blocked()->create(['name' => 'Arjun Mehta']);
        $admin = User::factory()->admin()->create(['name' => 'Asha Verma']);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertSee('aria-label="Block Priya Sharma"', false);
        $response->assertSee('aria-label="Unblock Arjun Mehta"', false);
        $response->assertDontSee('Block Asha Verma');
        $response->assertDontSee('Unblock Asha Verma');
    }

    public function test_escapes_user_names_in_the_list(): void
    {
        User::factory()->create(['name' => "<script>alert('xss')</script>"]);

        $response = $this->actingAs($this->admin())->get('/admin/users');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_shows_a_customer_with_what_they_spent_and_their_orders_newest_first(): void
    {
        $customer = User::factory()->create([
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
            'created_at' => '2026-10-05 09:00:00',
        ]);
        Order::factory()->for($customer)->create([
            'order_number' => 'ORD-10001',
            'total_amount' => 250,
            'created_at' => '2026-10-06 10:00:00',
        ]);
        Order::factory()->for($customer)->paidOnline()->status(OrderStatus::Shipped)->create([
            'order_number' => 'ORD-10002',
            'total_amount' => 500,
            'created_at' => '2026-10-07 14:05:00',
        ]);
        Order::factory()->create(['order_number' => 'ORD-10003']);

        $response = $this->actingAs($this->admin())->get("/admin/users/{$customer->id}");

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Priya Sharma', 'priya@example.com', 'Customer', 'Active', 'Block customer',
            'Registered on', '5 Oct 2026',
            'Orders', '2',
            'Total spent', '500.00',
            'ORD-10002', '7 Oct 2026, 2:05 PM', '500.00', 'Paid', 'Shipped',
            'ORD-10001', '6 Oct 2026, 10:00 AM', '250.00', 'Pending', 'Placed',
        ]);
        $response->assertDontSee('ORD-10003');
    }

    public function test_shows_ten_of_the_customers_orders_per_page(): void
    {
        $customer = User::factory()->create();
        Order::factory()->for($customer)->count(11)->sequence(fn ($sequence) => [
            'order_number' => sprintf('ORD-%05d', 20001 + $sequence->index),
            'created_at' => now()->subDays(11 - $sequence->index),
        ])->create();

        $firstPage = $this->actingAs($this->admin())->get("/admin/users/{$customer->id}");
        $secondPage = $this->actingAs($this->admin())->get("/admin/users/{$customer->id}?page=2");

        $firstPage->assertSee('ORD-20011');
        $firstPage->assertSee('ORD-20002');
        $firstPage->assertDontSee('ORD-20001');
        $secondPage->assertSee('ORD-20001');
        $secondPage->assertDontSee('ORD-20002');
    }

    public function test_says_when_a_customer_was_blocked_and_offers_to_unblock_them(): void
    {
        $customer = User::factory()->create(['blocked_at' => '2026-10-09 10:30:00']);

        $response = $this->actingAs($this->admin())->get("/admin/users/{$customer->id}");

        $response->assertOk();
        $response->assertSeeText('Blocked on 9 Oct 2026, 10:30 AM');
        $response->assertSee('Unblock customer');
        $response->assertDontSee('Block customer');
    }

    public function test_does_not_offer_to_block_an_admin(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($this->admin())->get("/admin/users/{$otherAdmin->id}");

        $response->assertOk();
        $response->assertSee('Admin accounts cannot be blocked.');
        $response->assertDontSee('Block customer');
    }

    public function test_says_so_when_the_user_has_no_orders(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($this->admin())->get("/admin/users/{$customer->id}");

        $response->assertOk();
        $response->assertSee('This user has not placed any orders.');
        $response->assertSeeTextInOrder(['Orders', '0', 'Total spent', '0.00']);
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/users/999999');

        $response->assertNotFound();
    }

    public function test_escapes_the_name_on_the_user_page(): void
    {
        $customer = User::factory()->create(['name' => "<script>alert('xss')</script>"]);

        $response = $this->actingAs($this->admin())->get("/admin/users/{$customer->id}");

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
