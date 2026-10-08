<?php

namespace Tests\Feature\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_payload_creates_a_customer_and_returns_201_with_a_token(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Priya Sharma');
        $response->assertJsonPath('data.email', 'priya@example.com');
        $response->assertJsonPath('data.role', 'customer');
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonMissingPath('data.password');

        $user = User::where('email', 'priya@example.com')->sole();
        $this->assertSame(UserRole::Customer, $user->role);

        $this->withToken($response->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_role_in_the_payload_does_not_create_an_admin(): void
    {
        $response = $this->postJson('/api/register', [...$this->validPayload(), 'role' => 'admin']);

        $response->assertJsonPath('data.role', 'customer');
        $this->assertSame(UserRole::Customer, User::where('email', 'priya@example.com')->sole()->role);
    }

    public function test_returns_422_with_required_errors_for_an_empty_payload(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'name' => 'The name field is required.',
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
        ]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_returns_422_when_the_email_is_already_registered(): void
    {
        User::factory()->create(['email' => 'priya@example.com']);

        $response = $this->postJson('/api/register', $this->validPayload());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
        $this->assertDatabaseCount('users', 1);
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
