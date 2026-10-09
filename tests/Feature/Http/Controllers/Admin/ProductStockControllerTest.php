<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_the_stock_of_a_product(): void
    {
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 5]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from('/admin/products')
            ->patch("/admin/products/{$product->id}/stock", ['stock' => '40']);

        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('status', 'Stock for "Gaming Laptop" set to 40.');
        $this->assertSame(40, $product->refresh()->stock);
    }

    public function test_allows_setting_the_stock_to_zero(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/products/{$product->id}/stock", ['stock' => '0']);

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, $product->refresh()->stock);
    }

    public function test_rejects_a_negative_stock_and_leaves_the_product_unchanged(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->patch("/admin/products/{$product->id}/stock", ['stock' => '-1']);

        $response->assertSessionHasErrors(['stock' => 'The stock field must be at least 0.']);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_forbids_customers_from_changing_the_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->actingAs(User::factory()->create())
            ->patch("/admin/products/{$product->id}/stock", ['stock' => '40']);

        $response->assertForbidden();
        $this->assertSame(5, $product->refresh()->stock);
    }
}
