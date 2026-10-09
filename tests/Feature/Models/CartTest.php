<?php

namespace Tests\Feature\Models;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_have_two_carts(): void
    {
        $user = User::factory()->create();
        Cart::factory()->for($user)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Cart::factory()->for($user)->create();
    }

    public function test_a_cart_cannot_hold_the_same_product_on_two_lines(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        CartItem::factory()->for($cart)->for($product)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        CartItem::factory()->for($cart)->for($product)->create();
    }

    public function test_total_adds_up_the_price_times_the_quantity_of_each_line(): void
    {
        $cart = Cart::factory()->create();
        $this->addLine($cart, Product::factory()->create(['price' => 19.99, 'stock' => 5]), 3);
        $this->addLine($cart, Product::factory()->create(['price' => 100.10, 'stock' => 5]), 2);
        $cart->load('items.product.category');

        $this->assertSame('260.17', $cart->total());
        $this->assertSame(5, $cart->totalQuantity());
        $this->assertFalse($cart->hasUnpurchasableItems());
    }

    public function test_total_leaves_out_lines_that_cannot_be_bought_as_they_stand(): void
    {
        $cart = Cart::factory()->create();
        $this->addLine($cart, Product::factory()->create(['price' => 100, 'stock' => 2]), 2);
        $this->addLine($cart, Product::factory()->inactive()->create(['price' => 1000, 'stock' => 5]), 1);
        $this->addLine($cart, Product::factory()->for(Category::factory()->inactive())->create(['price' => 2000, 'stock' => 5]), 1);
        $this->addLine($cart, Product::factory()->outOfStock()->create(['price' => 4000]), 1);
        $this->addLine($cart, Product::factory()->create(['price' => 8000, 'stock' => 1]), 2);
        $cart->load('items.product.category');

        $this->assertSame('200.00', $cart->total());
        $this->assertSame(7, $cart->totalQuantity());
        $this->assertTrue($cart->hasUnpurchasableItems());
    }

    public function test_deleting_a_user_removes_their_cart_and_its_items(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->for($user)->create();
        $item = CartItem::factory()->for($cart)->create();

        $user->delete();

        $this->assertModelMissing($cart);
        $this->assertModelMissing($item);
    }

    private function addLine(Cart $cart, Product $product, int $quantity): void
    {
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => $quantity]);
    }
}
