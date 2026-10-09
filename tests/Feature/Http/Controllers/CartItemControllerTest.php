<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_login_form_and_changes_nothing(): void
    {
        $item = CartItem::factory()->create(['quantity' => 1]);

        $this->post('/cart/items', ['product_id' => $item->product_id, 'quantity' => 1])->assertRedirectToRoute('login');
        $this->patch("/cart/items/{$item->id}", ['quantity' => 2])->assertRedirectToRoute('login');
        $this->delete("/cart/items/{$item->id}")->assertRedirectToRoute('login');

        $this->assertSame(1, $item->refresh()->quantity);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_forbids_admins_and_changes_nothing(): void
    {
        $item = CartItem::factory()->create(['quantity' => 1]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/cart/items', ['product_id' => $item->product_id, 'quantity' => 1])->assertForbidden();
        $this->actingAs($admin)->patch("/cart/items/{$item->id}", ['quantity' => 2])->assertForbidden();
        $this->actingAs($admin)->delete("/cart/items/{$item->id}")->assertForbidden();

        $this->assertSame(1, $item->refresh()->quantity);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_adds_a_product_and_returns_to_the_previous_page(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'slug' => 'gaming-laptop', 'stock' => 5]);

        $response = $this->actingAs($customer)
            ->from('/products/gaming-laptop')
            ->post('/cart/items', ['product_id' => $product->id, 'quantity' => '2']);

        $response->assertRedirect('/products/gaming-laptop');
        $response->assertSessionHas('status', '"Gaming Laptop" added to your cart.');
        $line = $customer->cartItems()->sole();
        $this->assertSame($product->id, $line->product_id);
        $this->assertSame(2, $line->quantity);
    }

    public function test_ignores_a_cart_id_in_the_form_and_adds_to_the_users_own_cart(): void
    {
        $customer = User::factory()->create();
        $otherCart = Cart::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($customer)
            ->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1, 'cart_id' => $otherCart->id]);

        $this->assertSame(1, $customer->cartItems()->count());
        $this->assertSame(0, $otherCart->items()->count());
    }

    public function test_refuses_more_than_is_in_stock_with_an_error_message(): void
    {
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'slug' => 'gaming-laptop', 'stock' => 1]);

        $response = $this->actingAs(User::factory()->create())
            ->from('/products/gaming-laptop')
            ->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2]);

        $response->assertRedirect('/products/gaming-laptop');
        $response->assertSessionHas('error', '"Gaming Laptop" has only 1 in stock.');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_refuses_a_product_customers_cannot_see_with_an_error_message(): void
    {
        $product = Product::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->create())
            ->from('/products')
            ->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $response->assertRedirect('/products');
        $response->assertSessionHas('error', 'This product is not available.');
        $this->assertDatabaseCount('cart_items', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidItems(): array
    {
        return [
            'no product' => [['product_id' => null], 'product_id', 'The product field is required.'],
            'product id that is not a number' => [['product_id' => 'abc'], 'product_id', 'The product field must be an integer.'],
            'no quantity' => [['quantity' => null], 'quantity', 'The quantity field is required.'],
            'quantity of zero' => [['quantity' => 0], 'quantity', 'The quantity field must be at least 1.'],
            'fractional quantity' => [['quantity' => '1.5'], 'quantity', 'The quantity field must be an integer.'],
            'quantity above the limit' => [
                ['quantity' => 1000001],
                'quantity',
                'The quantity field must not be greater than 1000000.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $invalid
     */
    #[DataProvider('invalidItems')]
    public function test_rejects_an_invalid_item_and_adds_nothing(array $invalid, string $field, string $message): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->actingAs(User::factory()->create())
            ->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1, ...$invalid]);

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_sets_the_quantity_of_a_line(): void
    {
        $item = CartItem::factory()->for(Product::factory()->create(['stock' => 5]))->create(['quantity' => 1]);

        $response = $this->actingAs($item->cart->user)->patch("/cart/items/{$item->id}", ['quantity' => '3']);

        $response->assertRedirectToRoute('cart.show');
        $response->assertSessionHas('status', 'Cart updated.');
        $this->assertSame(3, $item->refresh()->quantity);
    }

    public function test_refuses_a_quantity_above_the_stock_and_keeps_the_line(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 2]))
            ->create(['quantity' => 1]);

        $response = $this->actingAs($item->cart->user)->patch("/cart/items/{$item->id}", ['quantity' => 3]);

        $response->assertRedirectToRoute('cart.show');
        $response->assertSessionHas('error', '"Gaming Laptop" has only 2 in stock.');
        $this->assertSame(1, $item->refresh()->quantity);
    }

    public function test_rejects_a_quantity_below_one_and_keeps_the_line(): void
    {
        $item = CartItem::factory()->create(['quantity' => 2]);

        $response = $this->actingAs($item->cart->user)->patch("/cart/items/{$item->id}", ['quantity' => 0]);

        $response->assertSessionHasErrors(['quantity' => 'The quantity field must be at least 1.']);
        $this->assertSame(2, $item->refresh()->quantity);
    }

    public function test_json_request_sets_the_quantity_and_returns_the_rendered_cart(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->create(['name' => 'Gaming Laptop', 'price' => 250, 'stock' => 5]))
            ->create(['quantity' => 1]);

        $response = $this->actingAs($item->cart->user)->patchJson("/cart/items/{$item->id}", ['quantity' => 3]);

        $response->assertOk();
        $response->assertJsonPath('total_quantity', 3);
        $this->assertSame(3, $item->refresh()->quantity);

        $html = $response->json('html');
        $this->assertStringContainsString('Gaming Laptop', $html);
        $this->assertStringContainsString('750.00', $html);
        $this->assertStringNotContainsString('<html', $html);
    }

    public function test_json_request_returns_422_with_the_stock_message_and_keeps_the_line(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 2]))
            ->create(['quantity' => 1]);

        $response = $this->actingAs($item->cart->user)->patchJson("/cart/items/{$item->id}", ['quantity' => 3]);

        $response->assertUnprocessable();
        $response->assertExactJson(['message' => '"Gaming Laptop" has only 2 in stock.']);
        $this->assertSame(1, $item->refresh()->quantity);
    }

    public function test_json_request_returns_422_with_the_validation_message_for_a_quantity_below_one(): void
    {
        $item = CartItem::factory()->create(['quantity' => 2]);

        $response = $this->actingAs($item->cart->user)->patchJson("/cart/items/{$item->id}", ['quantity' => 0]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'The quantity field must be at least 1.');
        $this->assertSame(2, $item->refresh()->quantity);
    }

    public function test_updating_a_line_of_another_user_is_not_found(): void
    {
        $item = CartItem::factory()->for(Product::factory()->create(['stock' => 5]))->create(['quantity' => 1]);

        $response = $this->actingAs(User::factory()->create())->patch("/cart/items/{$item->id}", ['quantity' => 3]);

        $response->assertNotFound();
        $this->assertSame(1, $item->refresh()->quantity);
    }

    public function test_removes_a_line(): void
    {
        $item = CartItem::factory()->create();

        $response = $this->actingAs($item->cart->user)->delete("/cart/items/{$item->id}");

        $response->assertRedirectToRoute('cart.show');
        $response->assertSessionHas('status', 'Item removed from your cart.');
        $this->assertModelMissing($item);
    }

    public function test_removing_a_line_of_another_user_is_not_found(): void
    {
        $item = CartItem::factory()->create();

        $response = $this->actingAs(User::factory()->create())->delete("/cart/items/{$item->id}");

        $response->assertNotFound();
        $this->assertModelExists($item);
    }

    public function test_a_line_id_that_is_not_a_number_is_not_found(): void
    {
        $response = $this->actingAs(User::factory()->create())->delete('/cart/items/first');

        $response->assertNotFound();
    }
}
