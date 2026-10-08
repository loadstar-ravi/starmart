<?php

namespace Tests\Feature\Http\Controllers\Admin\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_admin_login_form_for_guests(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('Admin login');
    }

    public function test_valid_credentials_sign_the_admin_in(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password']);

        $response->assertRedirectToRoute('admin.dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_rejects_a_customer_on_the_admin_login_form(): void
    {
        $customer = User::factory()->create();

        $response = $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_wrong_password_returns_an_error_and_leaves_the_visitor_a_guest(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password']);

        $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_redirects_a_signed_in_admin_from_the_login_form_to_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/login');

        $response->assertRedirectToRoute('admin.dashboard');
    }

    public function test_logout_signs_the_admin_out(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/logout');

        $response->assertRedirectToRoute('admin.login');
        $this->assertGuest();
    }
}
