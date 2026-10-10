<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $response = $this->putJson('/api/user', ['name' => 'Priya Nair', 'email' => 'priya.nair@example.com']);

        $response->assertUnauthorized();
    }

    public function test_returns_403_and_changes_nothing_for_admins(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Asha Verma']);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/user', ['name' => 'Someone Else', 'email' => 'else@example.com']);

        $response->assertForbidden();
        $this->assertSame('Asha Verma', $admin->refresh()->name);
    }

    public function test_updates_the_name_and_email_and_returns_the_user(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);
        Sanctum::actingAs($customer);

        $response = $this->putJson('/api/user', ['name' => 'Priya Nair', 'email' => 'priya.nair@example.com']);

        $response->assertOk();
        $response->assertExactJson([
            'message' => 'Profile updated.',
            'data' => [
                'id' => $customer->id,
                'name' => 'Priya Nair',
                'email' => 'priya.nair@example.com',
                'role' => 'customer',
                'created_at' => $customer->created_at->toIso8601String(),
            ],
        ]);
        $customer->refresh();
        $this->assertSame('Priya Nair', $customer->name);
        $this->assertSame('priya.nair@example.com', $customer->email);
    }

    public function test_ignores_a_role_sent_in_the_payload(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->putJson('/api/user', [
            'name' => 'Priya Nair',
            'email' => 'priya.nair@example.com',
            'role' => 'admin',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.role', 'customer');
        $this->assertSame(UserRole::Customer, $customer->refresh()->role);
    }

    public function test_returns_422_with_required_errors_for_an_empty_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson('/api/user', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'name' => 'The name field is required.',
            'email' => 'The email field is required.',
        ]);
    }

    public function test_returns_422_when_the_email_belongs_to_another_user(): void
    {
        User::factory()->create(['email' => 'arjun@example.com']);
        $customer = User::factory()->create(['email' => 'priya@example.com']);
        Sanctum::actingAs($customer);

        $response = $this->putJson('/api/user', ['name' => 'Priya Sharma', 'email' => 'arjun@example.com']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
        $this->assertSame('priya@example.com', $customer->refresh()->email);
    }
}
