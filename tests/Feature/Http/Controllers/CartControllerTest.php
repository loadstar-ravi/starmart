<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_login_form(): void
    {
        $response = $this->get('/cart');

        $response->assertRedirectToRoute('login');
    }

    public function test_shows_an_empty_cart_without_saving_one(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/cart');

        $response->assertOk();
        $response->assertSee('Your cart is empty.');
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_lists_the_lines_with_their_prices_and_the_total(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)
            ->for(Product::factory()->create(['name' => 'Gaming Laptop', 'price' => 54990.50, 'stock' => 5]))
            ->create(['quantity' => 2]);
        CartItem::factory()->for($cart)
            ->for(Product::factory()->create(['name' => 'Wireless Mouse', 'price' => 799, 'stock' => 5]))
            ->create(['quantity' => 1]);

        $response = $this->actingAs($cart->user)->get('/cart');

        $response->assertOk();
        $response->assertSeeInOrder(['Gaming Laptop', '109,981.00', '54,990.50', 'Wireless Mouse', '799.00']);
        $response->assertSeeInOrder(['Summary', 'Items', '3', 'Total', '110,780.00']);
        $response->assertDontSee('left out of the total');
    }

    public function test_offers_to_lower_and_raise_a_quantity_by_one(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 9]))
            ->create(['quantity' => 4]);

        $response = $this->actingAs($item->cart->user)->get('/cart');

        $response->assertOk();
        $response->assertSeeInOrder([
            'name="quantity" value="3"',
            'aria-label="Decrease quantity of Gaming Laptop"',
            'name="quantity" value="5"',
            'aria-label="Increase quantity of Gaming Laptop"',
        ], false);
        $response->assertDontSee('aria-label="Decrease quantity of Gaming Laptop" disabled', false);
    }

    public function test_does_not_offer_to_lower_a_quantity_of_one(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->create(['name' => 'Gaming Laptop', 'stock' => 9]))
            ->create(['quantity' => 1]);

        $response = $this->actingAs($item->cart->user)->get('/cart');

        $response->assertOk();
        $response->assertSee('aria-label="Decrease quantity of Gaming Laptop" disabled', false);
    }

    public function test_lowering_a_quantity_above_the_stock_goes_straight_to_the_stock(): void
    {
        $item = CartItem::factory()->for(Product::factory()->create(['stock' => 2]))->create(['quantity' => 7]);

        $response = $this->actingAs($item->cart->user)->get('/cart');

        $response->assertOk();
        $response->assertSee('name="quantity" value="2"', false);
        $response->assertDontSee('name="quantity" value="6"', false);
    }

    public function test_does_not_show_the_lines_of_other_users(): void
    {
        CartItem::factory()->for(Product::factory()->create(['name' => 'Secret Tablet']))->create();

        $response = $this->actingAs(User::factory()->create())->get('/cart');

        $response->assertOk();
        $response->assertDontSee('Secret Tablet');
    }

    public function test_marks_a_product_customers_can_no_longer_see_and_offers_only_to_remove_it(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->for(Category::factory()->inactive())->create(['slug' => 'hidden-tablet']))
            ->create();

        $response = $this->actingAs($item->cart->user)->get('/cart');

        $response->assertOk();
        $response->assertSee('No longer available');
        $response->assertSee('left out of the total');
        $response->assertSee('Remove');
        $response->assertDontSee("quantity-{$item->id}");
        $response->assertDontSee(route('products.show', 'hidden-tablet'));
    }

    public function test_marks_a_product_that_ran_out_of_stock(): void
    {
        $item = CartItem::factory()->for(Product::factory()->outOfStock()->create())->create();

        $response = $this->actingAs($item->cart->user)->get('/cart');

        $response->assertOk();
        $response->assertSee('Out of stock');
        $response->assertSee('left out of the total');
        $response->assertDontSee("quantity-{$item->id}");
    }

    public function test_asks_to_lower_a_quantity_that_is_above_the_stock(): void
    {
        $item = CartItem::factory()->for(Product::factory()->create(['stock' => 1]))->create(['quantity' => 3]);

        $response = $this->actingAs($item->cart->user)->get('/cart');

        $response->assertOk();
        $response->assertSee('Only 1 left. Lower the quantity to buy this.');
        $response->assertSee('left out of the total');
        $response->assertSee("quantity-{$item->id}");
    }

    public function test_escapes_product_names(): void
    {
        $item = CartItem::factory()
            ->for(Product::factory()->create(['name' => "<script>alert('xss')</script>"]))
            ->create();

        $response = $this->actingAs($item->cart->user)->get('/cart');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }
}
