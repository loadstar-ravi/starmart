<?php

namespace Tests\Feature\Models;

use App\Models\Cart;
use App\Models\CartItem;
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

    public function test_deleting_a_user_removes_their_cart_and_its_items(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->for($user)->create();
        $item = CartItem::factory()->for($cart)->create();

        $user->delete();

        $this->assertModelMissing($cart);
        $this->assertModelMissing($item);
    }
}
