<?php

namespace Tests\Feature\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_token_that_authenticates_requests(): void
    {
        $customer = User::factory()->create();

        $response = $this->postJson('/api/login', ['email' => $customer->email, 'password' => 'password']);

        $response->assertOk();
        $response->assertJsonPath('data.id', $customer->id);
        $response->assertJsonPath('data.role', 'customer');
        $response->assertJsonPath('token_type', 'Bearer');

        $this->withToken($response->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $customer->email);
    }

    public function test_token_ability_matches_the_role_of_the_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();

        $this->assertSame(['admin'], $admin->tokens()->sole()->abilities);
    }

    public function test_returns_401_and_issues_no_token_for_a_wrong_password(): void
    {
        $customer = User::factory()->create();

        $response = $this->postJson('/api/login', ['email' => $customer->email, 'password' => 'wrong-password']);

        $response->assertUnauthorized();
        $response->assertExactJson(['message' => 'These credentials do not match our records.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_401_for_an_unknown_email(): void
    {
        $response = $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'password']);

        $response->assertUnauthorized();
    }

    public function test_returns_422_with_required_errors_for_an_empty_payload(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
        ]);
    }

    public function test_returns_429_after_five_failed_attempts_for_the_same_email(): void
    {
        $customer = User::factory()->create();
        $credentials = ['email' => $customer->email, 'password' => 'wrong-password'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', $credentials)->assertUnauthorized();
        }

        $this->postJson('/api/login', $credentials)->assertTooManyRequests();
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token_used_for_the_request(): void
    {
        $customer = User::factory()->create();
        $token = $customer->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/logout');

        $response->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
