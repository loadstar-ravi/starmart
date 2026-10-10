<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_login_and_register_links_to_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('login'));
        $response->assertSee(route('register'));
    }

    public function test_escapes_the_name_of_the_signed_in_customer(): void
    {
        $customer = User::factory()->create(['name' => "<script>alert('xss')</script>"]);

        $response = $this->actingAs($customer)->get('/');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_navigation_does_not_link_guests_to_the_cart_their_orders_or_a_profile(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee(route('cart.show'));
        $response->assertDontSee(route('orders.index'));
        $response->assertDontSee(route('profile.edit'));
    }

    public function test_navigation_does_not_link_admins_to_the_cart_their_orders_or_a_profile(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/');

        $response->assertOk();
        $response->assertDontSee(route('cart.show'));
        $response->assertDontSee(route('orders.index'));
        $response->assertDontSee(route('profile.edit'));
    }

    public function test_navigation_links_customers_to_their_orders_and_profile(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSee(route('orders.index'));
        $response->assertSee(route('profile.edit'));
    }

    public function test_navigation_shows_how_many_units_are_in_the_cart_of_the_signed_in_customer(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->create(['quantity' => 2]);
        CartItem::factory()->for($cart)->create(['quantity' => 3]);
        CartItem::factory()->create(['quantity' => 40]);

        $response = $this->actingAs($cart->user)->get('/');

        $response->assertOk();
        $response->assertSee(route('cart.show'));
        $response->assertSee('<span data-cart-count>5</span>', false);
    }

    public function test_navigation_shows_no_count_while_the_cart_is_empty(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSee(route('cart.show'));
        $response->assertDontSee('data-cart-count', false);
    }

    public function test_links_to_the_active_categories_only(): void
    {
        Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        Category::factory()->inactive()->create(['name' => 'Hidden Range', 'slug' => 'hidden-range']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('products.index', ['category' => 'electronics']));
        $response->assertDontSee('Hidden Range');
    }

    public function test_new_arrivals_show_only_products_customers_can_buy(): void
    {
        Product::factory()->create(['name' => 'Gaming Laptop']);
        Product::factory()->inactive()->create(['name' => 'Retired Phone']);
        Product::factory()->outOfStock()->create(['name' => 'Sold Out Camera']);
        Product::factory()->for(Category::factory()->inactive())->create(['name' => 'Hidden Tablet']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertDontSee('Retired Phone');
        $response->assertDontSee('Sold Out Camera');
        $response->assertDontSee('Hidden Tablet');
    }

    public function test_new_arrivals_show_the_eight_newest_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['name' => 'Oldest Product', 'created_at' => now()->subDays(10)]);
        Product::factory()->for($category)->count(8)->create(['created_at' => now()->subDay()]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewHas('newArrivals', fn ($products) => $products->count() === 8);
        $response->assertDontSee('Oldest Product');
    }
}
