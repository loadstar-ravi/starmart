<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivates_an_active_product(): void
    {
        $product = Product::factory()->create(['name' => 'Gaming Laptop']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from('/admin/products')
            ->patch("/admin/products/{$product->id}/status", ['status' => 'inactive']);

        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('status', 'Product "Gaming Laptop" deactivated.');
        $this->assertSame(ProductStatus::Inactive, $product->refresh()->status);
    }

    public function test_activates_an_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create(['name' => 'Gaming Laptop']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/products/{$product->id}/status", ['status' => 'active']);

        $response->assertSessionHas('status', 'Product "Gaming Laptop" activated.');
        $this->assertSame(ProductStatus::Active, $product->refresh()->status);
    }

    public function test_rejects_a_status_that_is_not_a_product_status(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/products/{$product->id}/status", ['status' => 'archived']);

        $response->assertSessionHasErrors(['status' => 'The selected status is invalid.']);
        $this->assertSame(ProductStatus::Active, $product->refresh()->status);
    }

    public function test_forbids_customers_from_changing_the_status(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->patch("/admin/products/{$product->id}/status", ['status' => 'inactive']);

        $response->assertForbidden();
        $this->assertSame(ProductStatus::Active, $product->refresh()->status);
    }
}
