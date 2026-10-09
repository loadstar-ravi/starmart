<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_login_form_for_guests(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Login');
    }

    public function test_valid_credentials_sign_the_customer_in(): void
    {
        $customer = User::factory()->create();

        $response = $this->post('/login', ['email' => $customer->email, 'password' => 'password']);

        $response->assertRedirectToRoute('home');
        $this->assertAuthenticatedAs($customer);
    }

    public function test_wrong_password_returns_an_error_and_leaves_the_visitor_a_guest(): void
    {
        $customer = User::factory()->create();

        $response = $this->post('/login', ['email' => $customer->email, 'password' => 'wrong-password']);

        $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_empty_payload_returns_required_errors(): void
    {
        $response = $this->post('/login', []);

        $response->assertSessionHasErrors([
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
        ]);
    }

    public function test_rejects_an_admin_on_the_customer_login_form(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_tells_a_blocked_customer_with_the_right_password_that_the_account_is_blocked(): void
    {
        $customer = User::factory()->blocked()->create();

        $response = $this->post('/login', ['email' => $customer->email, 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'Your account has been blocked. Please contact support.']);
        $this->assertGuest();
    }

    public function test_does_not_reveal_that_an_account_is_blocked_to_someone_with_the_wrong_password(): void
    {
        $customer = User::factory()->blocked()->create();

        $response = $this->post('/login', ['email' => $customer->email, 'password' => 'wrong-password']);

        $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_logout_signs_the_customer_out(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->post('/logout');

        $response->assertRedirectToRoute('home');
        $this->assertGuest();
    }

    public function test_logout_redirects_guests_to_the_login_form(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirectToRoute('login');
    }
}
