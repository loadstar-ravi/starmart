<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_registration_form_for_guests(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Create an account');
    }

    public function test_redirects_authenticated_customers_away_from_the_registration_form(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get('/register');

        $response->assertRedirectToRoute('home');
    }

    public function test_valid_payload_creates_a_customer_and_signs_them_in(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $response->assertRedirectToRoute('home');

        $user = User::where('email', 'priya@example.com')->sole();
        $this->assertSame('Priya Sharma', $user->name);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertTrue(Hash::check('secret-password', $user->password));

        $this->assertAuthenticatedAs($user);
    }

    public function test_role_in_the_payload_does_not_create_an_admin(): void
    {
        $this->post('/register', [...$this->validPayload(), 'role' => 'admin']);

        $this->assertSame(UserRole::Customer, User::where('email', 'priya@example.com')->sole()->role);
    }

    public function test_empty_payload_returns_required_errors_and_creates_no_user(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_rejects_an_email_that_is_already_registered(): void
    {
        User::factory()->create(['email' => 'priya@example.com']);

        $response = $this->post('/register', $this->validPayload());

        $response->assertSessionHasErrors(['email' => 'The email has already been taken.']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_rejects_a_password_confirmation_that_does_not_match(): void
    {
        $response = $this->post('/register', [...$this->validPayload(), 'password_confirmation' => 'different-password']);

        $response->assertSessionHasErrors(['password' => 'The password field confirmation does not match.']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_a_password_shorter_than_eight_characters(): void
    {
        $response = $this->post('/register', [
            ...$this->validPayload(),
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors(['password' => 'The password field must be at least 8 characters.']);
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array{name: string, email: string, password: string, password_confirmation: string}
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];
    }
}
