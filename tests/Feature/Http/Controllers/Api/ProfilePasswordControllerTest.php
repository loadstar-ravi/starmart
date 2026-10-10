<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    private const array NEW_PASSWORD = [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ];

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $response = $this->putJson('/api/user/password', self::NEW_PASSWORD);

        $response->assertUnauthorized();
    }

    public function test_returns_403_for_admins_and_keeps_their_password(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/user/password', self::NEW_PASSWORD);

        $response->assertForbidden();
        $this->assertTrue(Hash::check('password', $admin->refresh()->password));
    }

    public function test_changes_the_password(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->putJson('/api/user/password', self::NEW_PASSWORD);

        $response->assertOk();
        $response->assertExactJson(['message' => 'Password changed.']);
        $this->assertTrue(Hash::check('new-password-123', $customer->refresh()->password));
    }

    public function test_the_token_that_changed_the_password_keeps_working(): void
    {
        $customer = User::factory()->create();
        $token = $customer->createToken('api-token')->plainTextToken;

        $this->withToken($token)->putJson('/api/user/password', self::NEW_PASSWORD)->assertOk();

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $customer->id);
    }

    public function test_returns_422_and_keeps_the_password_when_the_current_password_is_wrong(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->putJson('/api/user/password', [...self::NEW_PASSWORD, 'current_password' => 'not-my-password']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['current_password' => 'The password is incorrect.']);
        $this->assertTrue(Hash::check('password', $customer->refresh()->password));
    }

    public function test_returns_422_with_required_errors_for_an_empty_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson('/api/user/password', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'current_password' => 'The current password field is required.',
            'password' => 'The new password field is required.',
        ]);
    }

    public function test_returns_422_when_the_new_password_is_not_confirmed(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->putJson('/api/user/password', [...self::NEW_PASSWORD, 'password_confirmation' => 'something-else-123']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['password' => 'The new password field confirmation does not match.']);
        $this->assertTrue(Hash::check('password', $customer->refresh()->password));
    }
}
