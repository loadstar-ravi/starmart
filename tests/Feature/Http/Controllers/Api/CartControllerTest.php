<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();
    }

    public function test_returns_an_empty_cart_without_saving_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/cart');

        $response->assertOk();
        $response->assertExactJson(['data' => ['items' => [], 'total_quantity' => 0, 'total' => '0.00']]);
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_returns_the_lines_with_their_products_and_the_totals(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $laptop = Product::factory()->for($category)->create([
            'name' => 'Gaming Laptop',
            'slug' => 'gaming-laptop',
            'description' => 'A fast laptop.',
            'price' => 54990.50,
            'stock' => 5,
        ]);
        $cart = Cart::factory()->create();
        $laptopLine = CartItem::factory()->for($cart)->for($laptop)->create(['quantity' => 2]);
        CartItem::factory()->for($cart)
            ->for(Product::factory()->create(['price' => 799, 'stock' => 5]))
            ->create(['quantity' => 1]);
        Sanctum::actingAs($cart->user);

        $response = $this->getJson('/api/cart');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.items');
        $response->assertJsonPath('data.items.0', [
            'id' => $laptopLine->id,
            'quantity' => 2,
            'line_total' => '109981.00',
            'is_purchasable' => true,
            'product' => [
                'id' => $laptop->id,
                'name' => 'Gaming Laptop',
                'slug' => 'gaming-laptop',
                'description' => 'A fast laptop.',
                'price' => '54990.50',
                'stock' => 5,
                'in_stock' => true,
                'category' => ['id' => $category->id, 'name' => 'Electronics', 'slug' => 'electronics'],
                'image_url' => null,
                'created_at' => $laptop->created_at->toIso8601String(),
            ],
        ]);
        $response->assertJsonPath('data.total_quantity', 3);
        $response->assertJsonPath('data.total', '110780.00');
    }

    public function test_leaves_out_the_lines_of_other_users(): void
    {
        CartItem::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/cart');

        $response->assertOk();
        $response->assertJsonCount(0, 'data.items');
    }

    public function test_flags_lines_that_cannot_be_bought_and_leaves_them_out_of_the_total(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)
            ->for(Product::factory()->create(['price' => 100, 'stock' => 5]))
            ->create(['quantity' => 2]);
        CartItem::factory()->for($cart)
            ->for(Product::factory()->inactive()->create(['price' => 500, 'stock' => 5]))
            ->create(['quantity' => 1]);
        Sanctum::actingAs($cart->user);

        $response = $this->getJson('/api/cart');

        $response->assertOk();
        $this->assertSame([true, false], $response->json('data.items.*.is_purchasable'));
        $response->assertJsonPath('data.total_quantity', 3);
        $response->assertJsonPath('data.total', '200.00');
    }
}
