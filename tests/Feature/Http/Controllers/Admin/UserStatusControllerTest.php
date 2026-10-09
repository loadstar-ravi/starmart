<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_admin_login_form_and_blocks_nobody(): void
    {
        $customer = User::factory()->create();

        $response = $this->patch("/admin/users/{$customer->id}/status", ['blocked' => 1]);

        $response->assertRedirectToRoute('admin.login');
        $this->assertFalse($customer->refresh()->isBlocked());
    }

    public function test_forbids_customers_from_blocking_anyone(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->patch("/admin/users/{$customer->id}/status", ['blocked' => 1]);

        $response->assertForbidden();
        $this->assertFalse($customer->refresh()->isBlocked());
    }

    public function test_blocks_a_customer_and_revokes_their_api_tokens(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sharma']);
        $customer->createToken('phone');

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from('/admin/users')
            ->patch("/admin/users/{$customer->id}/status", ['blocked' => '1']);

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('status', 'Priya Sharma is blocked and can no longer log in.');
        $this->assertTrue($customer->refresh()->isBlocked());
        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_unblocks_a_customer(): void
    {
        $customer = User::factory()->blocked()->create(['name' => 'Priya Sharma']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from("/admin/users/{$customer->id}")
            ->patch("/admin/users/{$customer->id}/status", ['blocked' => '0']);

        $response->assertRedirect("/admin/users/{$customer->id}");
        $response->assertSessionHas('status', 'Priya Sharma is unblocked and can log in again.');
        $this->assertFalse($customer->refresh()->isBlocked());
    }

    public function test_forbids_blocking_another_admin(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/users/{$otherAdmin->id}/status", ['blocked' => 1]);

        $response->assertForbidden();
        $this->assertFalse($otherAdmin->refresh()->isBlocked());
    }

    public function test_forbids_admins_from_blocking_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch("/admin/users/{$admin->id}/status", ['blocked' => 1]);

        $response->assertForbidden();
        $this->assertFalse($admin->refresh()->isBlocked());
    }

    #[TestWith([[], 'The blocked field is required.'], 'nothing sent')]
    #[TestWith([['blocked' => 'yes'], 'The blocked field must be true or false.'], 'not a yes or no value')]
    public function test_rejects_a_request_that_does_not_say_whether_to_block(array $payload, string $message): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/users/{$customer->id}/status", $payload);

        $response->assertSessionHasErrors(['blocked' => $message]);
        $this->assertFalse($customer->refresh()->isBlocked());
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/users/999999/status', ['blocked' => 1]);

        $response->assertNotFound();
    }
}
