<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureUserIsNotBlockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_signs_out_a_customer_who_was_blocked_while_signed_in(): void
    {
        $customer = User::factory()->blocked()->create();

        $response = $this->actingAs($customer)->get('/products');

        $response->assertRedirectToRoute('login');
        $response->assertSessionHas('error', 'Your account has been blocked. Please contact support.');
        $this->assertGuest();
    }

    public function test_shows_the_reason_on_the_login_form(): void
    {
        $customer = User::factory()->blocked()->create();

        $response = $this->actingAs($customer)->followingRedirects()->get('/cart');

        $response->assertOk();
        $response->assertSee('Your account has been blocked. Please contact support.');
    }

    public function test_lets_a_customer_who_is_not_blocked_through(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get('/products');

        $response->assertOk();
        $this->assertAuthenticatedAs($customer);
    }

    public function test_sends_a_blocked_user_on_an_admin_page_to_the_admin_login_form(): void
    {
        $admin = User::factory()->admin()->blocked()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertRedirectToRoute('admin.login');
        $this->assertGuest();
    }
}
