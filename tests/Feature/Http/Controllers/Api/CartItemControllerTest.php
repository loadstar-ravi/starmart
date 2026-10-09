<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_and_changes_nothing_when_no_token_is_provided(): void
    {
        $item = CartItem::factory()->create(['quantity' => 1]);

        $this->postJson('/api/cart', ['product_id' => $item->product_id, 'quantity' => 1])->assertUnauthorized();
        $this->putJson("/api/cart/{$item->id}", ['quantity' => 2])->assertUnauthorized();
        $this->deleteJson("/api/cart/{$item->id}")->assertUnauthorized();

        $this->assertSame(1, $item->refresh()->quantity);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_returns_403_and_changes_nothing_for_admins(): void
    {
        $item = CartItem::factory()->create(['quantity' => 1]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/cart', ['product_id' => $item->product_id, 'quantity' => 1])->assertForbidden();
        $this->putJson("/api/cart/{$item->id}", ['quantity' => 2])->assertForbidden();
        $this->deleteJson("/api/cart/{$item->id}")->assertForbidden();

        $this->assertSame(1, $item->refresh()->quantity);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_adds_a_product_and_returns_201_with_the_cart(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 250, 'stock' => 5]);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $response->assertCreated();
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.product.id', $product->id);
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.total_quantity', 2);
        $response->assertJsonPath('data.total', '500.00');
        $this->assertSame(2, $customer->cartItems()->sole()->quantity);
    }

    public function test_adding_a_product_already_in_the_cart_returns_201_with_one_line_of_the_raised_quantity(): void
    {
        $item = CartItem::factory()->for(Product::factory()->create(['stock' => 5]))->create(['quantity' => 1]);
        Sanctum::actingAs($item->cart->user);

        $response = $this->postJson('/api/cart', ['product_id' => $item->product_id, 'quantity' => 2]);

        $response->assertCreated();
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.quantity', 3);
        $this->assertSame(3, $item->refresh()->quantity);
    }

    public function test_ignores_a_cart_id_in_the_payload_and_adds_to_the_users_own_cart(): void
    {
        $customer = User::factory()->create();
        $otherCart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
            'cart_id' => $otherCart->id,
        ]);

        $response->assertCreated();
        $this->assertSame(1, $customer->cartItems()->count());
        $this->assertSame(0, $otherCart->items()->count());
    }

    public function test_returns_422_when_adding_more_than_is_in_stock(): void
    {
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 1]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => '"Gaming Laptop" has only 1 in stock.']);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_returns_422_when_adding_a_product_customers_cannot_see(): void
    {
        $product = Product::factory()->inactive()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1]);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => 'This product is not available.']);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_returns_422_with_required_errors_for_an_empty_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/cart', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'product_id' => 'The product field is required.',
            'quantity' => 'The quantity field is required.',
        ]);
    }

    public function test_sets_the_quantity_of_a_line_and_returns_the_cart(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->create(['price' => 250, 'stock' => 5]))
            ->create(['quantity' => 1]);
        Sanctum::actingAs($item->cart->user);

        $response = $this->putJson("/api/cart/{$item->id}", ['quantity' => 3]);

        $response->assertOk();
        $response->assertJsonPath('data.items.0.id', $item->id);
        $response->assertJsonPath('data.items.0.quantity', 3);
        $response->assertJsonPath('data.total', '750.00');
        $this->assertSame(3, $item->refresh()->quantity);
    }

    public function test_returns_422_when_setting_a_quantity_above_the_stock(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 2]))
            ->create(['quantity' => 1]);
        Sanctum::actingAs($item->cart->user);

        $response = $this->putJson("/api/cart/{$item->id}", ['quantity' => 3]);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => '"Gaming Laptop" has only 2 in stock.']);
        $this->assertSame(1, $item->refresh()->quantity);
    }

    public function test_returns_422_with_a_validation_error_for_a_quantity_below_one(): void
    {
        $item = CartItem::factory()->create(['quantity' => 2]);
        Sanctum::actingAs($item->cart->user);

        $response = $this->putJson("/api/cart/{$item->id}", ['quantity' => 0]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['quantity' => 'The quantity field must be at least 1.']);
        $this->assertSame(2, $item->refresh()->quantity);
    }

    public function test_returns_404_when_updating_a_line_of_another_user(): void
    {
        $item = CartItem::factory()->for(Product::factory()->create(['stock' => 5]))->create(['quantity' => 1]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson("/api/cart/{$item->id}", ['quantity' => 3]);

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'The requested resource was not found.']);
        $this->assertSame(1, $item->refresh()->quantity);
    }

    public function test_removes_a_line_and_returns_the_remaining_cart(): void
    {
        $cart = Cart::factory()->create();
        $removed = CartItem::factory()->for($cart)->create();
        $kept = CartItem::factory()->for($cart)->create();
        Sanctum::actingAs($cart->user);

        $response = $this->deleteJson("/api/cart/{$removed->id}");

        $response->assertOk();
        $this->assertSame([$kept->id], $response->json('data.items.*.id'));
        $this->assertModelMissing($removed);
    }

    public function test_returns_404_when_removing_a_line_of_another_user(): void
    {
        $item = CartItem::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->deleteJson("/api/cart/{$item->id}");

        $response->assertNotFound();
        $this->assertModelExists($item);
    }
}
