<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_from_admin_pages_to_the_admin_login_form(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirectToRoute('admin.login');
    }

    public function test_forbids_customers_from_admin_pages(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get('/admin');

        $response->assertForbidden();
    }

    public function test_allows_admins_onto_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Dashboard');
    }

    public function test_returns_401_from_admin_apis_when_no_token_is_provided(): void
    {
        $this->registerAdminApiRoute();

        $response = $this->getJson('/api/admin/ping');

        $response->assertUnauthorized();
    }

    public function test_returns_403_from_admin_apis_for_customers(): void
    {
        $this->registerAdminApiRoute();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/admin/ping');

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Only admins can do this.');
    }

    public function test_allows_admins_onto_admin_apis(): void
    {
        $this->registerAdminApiRoute();
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/admin/ping');

        $response->assertOk();
    }

    /**
     * No admin API endpoint exists yet, so the middleware stack is exercised through a stand-in route.
     */
    private function registerAdminApiRoute(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'admin'])
            ->get('/api/admin/ping', fn () => response()->json(['ok' => true]));
    }
}
