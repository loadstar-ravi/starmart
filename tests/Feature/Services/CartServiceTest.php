<?php

namespace Tests\Feature\Services;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_product_creates_the_users_cart_with_one_line(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $item = (new CartService)->addProduct($user, $product->id, 2);

        $this->assertTrue($user->cartItems()->sole()->is($item));
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(2, $item->quantity);
    }

    public function test_adding_a_product_already_in_the_cart_raises_its_quantity_up_to_the_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $line = $this->putInCart($user, $product, 2);

        (new CartService)->addProduct($user, $product->id, 3);

        $this->assertSame(5, $line->refresh()->quantity);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_adding_a_product_leaves_the_same_product_in_another_users_cart_unchanged(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $otherLine = $this->putInCart(User::factory()->create(), $product, 1);
        $user = User::factory()->create();

        (new CartService)->addProduct($user, $product->id, 2);

        $this->assertSame(2, $user->cartItems()->sole()->quantity);
        $this->assertSame(1, $otherLine->refresh()->quantity);
    }

    /**
     * @return array<string, array{0: Closure(): int}>
     */
    public static function productsCustomersCannotBuy(): array
    {
        return [
            'inactive product' => [fn (): int => Product::factory()->inactive()->create()->id],
            'product in an inactive category' => [
                fn (): int => Product::factory()->for(Category::factory()->inactive())->create()->id,
            ],
            'product that does not exist' => [fn (): int => 999999],
        ];
    }

    /**
     * @param  Closure(): int  $productId
     */
    #[DataProvider('productsCustomersCannotBuy')]
    public function test_refuses_to_add_a_product_customers_cannot_buy(Closure $productId): void
    {
        $user = User::factory()->create();
        $id = $productId();

        $message = $this->refusalOf(fn () => (new CartService)->addProduct($user, $id, 1));

        $this->assertSame('This product is not available.', $message);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_refuses_to_add_more_than_is_in_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 3]);

        $message = $this->refusalOf(fn () => (new CartService)->addProduct($user, $product->id, 4));

        $this->assertSame('"Gaming Laptop" has only 3 in stock.', $message);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_refuses_to_add_when_the_cart_and_the_new_quantity_together_exceed_the_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 3]);
        $line = $this->putInCart($user, $product, 2);

        $message = $this->refusalOf(fn () => (new CartService)->addProduct($user, $product->id, 2));

        $this->assertSame('"Gaming Laptop" has only 3 in stock, and your cart already has 2.', $message);
        $this->assertSame(2, $line->refresh()->quantity);
    }

    public function test_refuses_to_add_a_product_that_is_out_of_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->outOfStock()->create(['name' => 'Gaming Laptop']);

        $message = $this->refusalOf(fn () => (new CartService)->addProduct($user, $product->id, 1));

        $this->assertSame('"Gaming Laptop" is out of stock.', $message);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_updating_sets_the_quantity_of_the_line_up_to_the_stock(): void
    {
        $user = User::factory()->create();
        $line = $this->putInCart($user, Product::factory()->create(['stock' => 5]), 1);

        (new CartService)->updateQuantity($user, $line->id, 5);

        $this->assertSame(5, $line->refresh()->quantity);
    }

    public function test_refuses_to_update_to_more_than_is_in_stock(): void
    {
        $user = User::factory()->create();
        $line = $this->putInCart($user, Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 5]), 1);

        $message = $this->refusalOf(fn () => (new CartService)->updateQuantity($user, $line->id, 6));

        $this->assertSame('"Gaming Laptop" has only 5 in stock.', $message);
        $this->assertSame(1, $line->refresh()->quantity);
    }

    public function test_refuses_to_update_a_line_whose_product_customers_can_no_longer_buy(): void
    {
        $user = User::factory()->create();
        $line = $this->putInCart($user, Product::factory()->inactive()->create(['stock' => 5]), 1);

        $message = $this->refusalOf(fn () => (new CartService)->updateQuantity($user, $line->id, 2));

        $this->assertSame('This product is not available.', $message);
        $this->assertSame(1, $line->refresh()->quantity);
    }

    public function test_updating_a_line_in_another_users_cart_is_not_found_and_changes_nothing(): void
    {
        $line = $this->putInCart(User::factory()->create(), Product::factory()->create(['stock' => 5]), 1);
        $intruder = User::factory()->create();

        try {
            (new CartService)->updateQuantity($intruder, $line->id, 3);
            $this->fail('A line in another user\'s cart should not be found.');
        } catch (ModelNotFoundException) {
            $this->assertSame(1, $line->refresh()->quantity);
        }
    }

    public function test_removing_deletes_the_line_and_keeps_the_others(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->for($user)->create();
        $removed = CartItem::factory()->for($cart)->create();
        $kept = CartItem::factory()->for($cart)->create();

        (new CartService)->removeItem($user, $removed->id);

        $this->assertModelMissing($removed);
        $this->assertModelExists($kept);
    }

    public function test_removing_a_line_in_another_users_cart_is_not_found_and_changes_nothing(): void
    {
        $line = $this->putInCart(User::factory()->create(), Product::factory()->create(), 1);
        $intruder = User::factory()->create();

        try {
            (new CartService)->removeItem($intruder, $line->id);
            $this->fail('A line in another user\'s cart should not be found.');
        } catch (ModelNotFoundException) {
            $this->assertModelExists($line);
        }
    }

    public function test_cart_of_a_user_who_added_nothing_is_empty_and_not_saved(): void
    {
        $user = User::factory()->create();

        $cart = (new CartService)->cartFor($user);

        $this->assertCount(0, $cart->items);
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_cart_holds_only_the_lines_of_its_own_user(): void
    {
        $user = User::factory()->create();
        $ownLine = $this->putInCart($user, Product::factory()->create(), 1);
        $this->putInCart(User::factory()->create(), Product::factory()->create(), 1);

        $cart = (new CartService)->cartFor($user);

        $this->assertTrue($cart->items->sole()->is($ownLine));
    }

    private function putInCart(User $user, Product $product, int $quantity): CartItem
    {
        return CartItem::factory()
            ->for(Cart::factory()->for($user))
            ->for($product)
            ->create(['quantity' => $quantity]);
    }

    /**
     * Run a cart change that must be refused and return the message the customer would get.
     */
    private function refusalOf(Closure $change): string
    {
        try {
            $change();
        } catch (CartException $exception) {
            return $exception->getMessage();
        }

        $this->fail('The cart change should have been refused.');
    }
}
