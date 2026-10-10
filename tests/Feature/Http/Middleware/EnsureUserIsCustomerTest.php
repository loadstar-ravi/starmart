<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnsureUserIsCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbids_admins_from_customer_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/cart');

        $response->assertForbidden();
    }

    public function test_allows_customers_onto_customer_pages(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get('/cart');

        $response->assertOk();
    }

    public function test_returns_403_from_customer_apis_for_admins(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/cart');

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Only customers can do this.');
    }

    public function test_allows_customers_onto_customer_apis(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/cart');

        $response->assertOk();
    }
}
