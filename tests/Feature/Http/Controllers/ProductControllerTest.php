<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_products_with_the_filter_form_to_guests(): void
    {
        Product::factory()
            ->for(Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']))
            ->create(['name' => 'Gaming Laptop']);
        Product::factory()->create(['name' => 'Cotton Shirt']);

        $response = $this->get('/products');

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertSee('Cotton Shirt');
        $response->assertSee('of 2 products');
        $response->assertSee('Filter products');
        $response->assertSee('<option value="electronics"', false);
    }

    public function test_hides_inactive_products(): void
    {
        Product::factory()->create(['name' => 'Gaming Laptop']);
        Product::factory()->inactive()->create(['name' => 'Retired Phone']);

        $response = $this->get('/products');

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertDontSee('Retired Phone');
    }

    public function test_hides_products_and_filter_options_of_inactive_categories(): void
    {
        Product::factory()->create(['name' => 'Gaming Laptop']);
        Product::factory()
            ->for(Category::factory()->inactive()->create(['name' => 'Hidden Range']))
            ->create(['name' => 'Hidden Tablet']);

        $response = $this->get('/products');

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertDontSee('Hidden Tablet');
        $response->assertDontSee('Hidden Range');
    }

    public function test_search_lists_only_products_whose_name_contains_the_term(): void
    {
        Product::factory()->create(['name' => 'Gaming Laptop']);
        Product::factory()->create(['name' => 'Cotton Shirt']);

        $response = $this->get('/products?search=laptop');

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertDontSee('Cotton Shirt');
    }

    public function test_category_filter_lists_only_products_of_that_category(): void
    {
        $electronics = Category::factory()->create(['slug' => 'electronics']);
        Product::factory()->for($electronics)->create(['name' => 'Gaming Laptop']);
        Product::factory()->create(['name' => 'Cotton Shirt']);

        $response = $this->get('/products?category=electronics');

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertDontSee('Cotton Shirt');
    }

    public function test_minimum_price_lists_products_at_or_above_it(): void
    {
        $this->createProductsPricedAt500And1000And1500();

        $response = $this->get('/products?min_price=1000');

        $response->assertOk();
        $response->assertDontSee('Budget Mouse');
        $response->assertSee('Mid Keyboard');
        $response->assertSee('Premium Monitor');
    }

    public function test_maximum_price_lists_products_at_or_below_it(): void
    {
        $this->createProductsPricedAt500And1000And1500();

        $response = $this->get('/products?max_price=1000');

        $response->assertOk();
        $response->assertSee('Budget Mouse');
        $response->assertSee('Mid Keyboard');
        $response->assertDontSee('Premium Monitor');
    }

    public function test_price_range_lists_only_products_inside_it(): void
    {
        $this->createProductsPricedAt500And1000And1500();

        $response = $this->get('/products?min_price=600&max_price=1400');

        $response->assertOk();
        $response->assertDontSee('Budget Mouse');
        $response->assertSee('Mid Keyboard');
        $response->assertDontSee('Premium Monitor');
    }

    public function test_lists_the_newest_products_first_by_default(): void
    {
        Product::factory()->create(['name' => 'Older Product', 'created_at' => now()->subDays(2)]);
        Product::factory()->create(['name' => 'Newer Product', 'created_at' => now()->subDay()]);

        $response = $this->get('/products');

        $response->assertSeeInOrder(['Newer Product', 'Older Product']);
    }

    public function test_sorts_by_price_from_low_to_high(): void
    {
        $this->createProductsPricedAt500And1000And1500();

        $response = $this->get('/products?sort=price_asc');

        $response->assertSeeInOrder(['Budget Mouse', 'Mid Keyboard', 'Premium Monitor']);
    }

    public function test_sorts_by_price_from_high_to_low(): void
    {
        $this->createProductsPricedAt500And1000And1500();

        $response = $this->get('/products?sort=price_desc');

        $response->assertSeeInOrder(['Premium Monitor', 'Mid Keyboard', 'Budget Mouse']);
    }

    public function test_shows_twelve_products_per_page_and_keeps_the_filters_in_the_page_links(): void
    {
        Product::factory()
            ->count(13)
            ->sequence(fn ($sequence) => [
                'name' => sprintf('Item %02d', $sequence->index + 1),
                'price' => ($sequence->index + 1) * 100,
            ])
            ->create();

        $firstPage = $this->get('/products?sort=price_asc');
        $secondPage = $this->get('/products?sort=price_asc&page=2');

        $firstPage->assertSee('Item 12');
        $firstPage->assertDontSee('Item 13');
        $firstPage->assertSee('of 13 products');
        $firstPage->assertSee('sort=price_asc&amp;page=2', false);

        $secondPage->assertSee('Item 13');
        $secondPage->assertDontSee('Item 12');
    }

    public function test_marks_products_without_stock_as_out_of_stock(): void
    {
        Product::factory()->outOfStock()->create(['name' => 'Sold Out Camera']);

        $response = $this->get('/products');

        $response->assertSee('Sold Out Camera');
        $response->assertSee('Out of stock');
    }

    public function test_does_not_mark_products_with_stock_as_out_of_stock(): void
    {
        Product::factory()->create(['stock' => 1]);

        $response = $this->get('/products');

        $response->assertOk();
        $response->assertDontSee('Out of stock');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'maximum price below the minimum' => [
                'min_price=500&max_price=100',
                'max_price',
                'The maximum price must not be less than the minimum price.',
            ],
            'negative minimum price' => ['min_price=-1', 'min_price', 'The minimum price field must be at least 0.'],
            'minimum price that is not a number' => ['min_price=abc', 'min_price', 'The minimum price field must be a number.'],
            'unknown sort order' => ['sort=popular', 'sort', 'The selected sort is invalid.'],
            'unknown category' => ['category=missing', 'category', 'The selected category is invalid.'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_rejects_an_invalid_filter(string $query, string $field, string $message): void
    {
        $response = $this->get("/products?{$query}");

        $response->assertSessionHasErrors([$field => $message]);
    }

    public function test_rejects_the_slug_of_an_inactive_category(): void
    {
        Category::factory()->inactive()->create(['slug' => 'hidden-range']);

        $response = $this->get('/products?category=hidden-range');

        $response->assertSessionHasErrors(['category' => 'The selected category is invalid.']);
    }

    public function test_json_request_returns_only_the_rendered_results(): void
    {
        Product::factory()->create(['name' => 'Gaming Laptop']);
        Product::factory()->create(['name' => 'Cotton Shirt']);

        $response = $this->getJson('/products?search=laptop');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertHeader('Vary', 'Accept');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $html = $response->json('html');
        $this->assertStringContainsString('Gaming Laptop', $html);
        $this->assertStringNotContainsString('Cotton Shirt', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('Filter products', $html);
    }

    public function test_json_request_returns_422_with_the_validation_message_for_an_invalid_filter(): void
    {
        $response = $this->getJson('/products?min_price=500&max_price=100');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'max_price' => 'The maximum price must not be less than the minimum price.',
        ]);
    }

    public function test_escapes_product_names_and_the_search_term(): void
    {
        Product::factory()->create(['name' => "<script>alert('xss')</script>"]);

        $page = $this->get('/products?search='.urlencode("<script>alert('xss')</script>"));
        $fragment = $this->getJson('/products');

        $page->assertOk();
        $page->assertSee('&lt;script&gt;', false);
        $page->assertDontSee("<script>alert('xss')</script>", false);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $fragment->json('html'));
    }

    public function test_shows_the_details_of_a_product(): void
    {
        $product = Product::factory()
            ->for(Category::factory()->create(['name' => 'Electronics']))
            ->create([
                'name' => 'Gaming Laptop',
                'slug' => 'gaming-laptop',
                'description' => 'A fast laptop for work and play.',
                'price' => 54990.50,
                'stock' => 20,
            ]);

        $response = $this->get('/products/gaming-laptop');

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertSee('Electronics');
        $response->assertSee('54,990.50');
        $response->assertSee('A fast laptop for work and play.');
        $response->assertSee('In stock');
        $response->assertViewHas('product', fn (Product $shown) => $shown->is($product));
    }

    public function test_details_show_how_many_are_left_when_stock_is_low(): void
    {
        Product::factory()->create(['slug' => 'gaming-laptop', 'stock' => 3]);

        $response = $this->get('/products/gaming-laptop');

        $response->assertSee('Only 3 left');
        $response->assertDontSee('In stock');
    }

    public function test_details_mark_a_product_without_stock_as_out_of_stock(): void
    {
        Product::factory()->outOfStock()->create(['slug' => 'gaming-laptop']);

        $response = $this->get('/products/gaming-laptop');

        $response->assertOk();
        $response->assertSee('Out of stock');
    }

    public function test_details_use_the_primary_image_as_the_main_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['slug' => 'gaming-laptop']);
        ProductImage::factory()->for($product)->create(['sort_order' => 1]);
        $primary = ProductImage::factory()->for($product)->primary()->create(['sort_order' => 2]);

        $response = $this->get('/products/gaming-laptop');

        $response->assertOk();
        $response->assertViewHas('mainImage', fn (ProductImage $image) => $image->is($primary));
        $response->assertSee($primary->url);
    }

    public function test_details_escape_the_description(): void
    {
        Product::factory()->create(['slug' => 'gaming-laptop', 'description' => "<script>alert('xss')</script>"]);

        $response = $this->get('/products/gaming-laptop');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_details_of_an_inactive_product_are_not_found(): void
    {
        Product::factory()->inactive()->create(['slug' => 'retired-phone']);

        $this->get('/products/retired-phone')->assertNotFound();
    }

    public function test_details_of_a_product_in_an_inactive_category_are_not_found(): void
    {
        Product::factory()->for(Category::factory()->inactive())->create(['slug' => 'hidden-tablet']);

        $this->get('/products/hidden-tablet')->assertNotFound();
    }

    public function test_details_of_an_unknown_product_are_not_found(): void
    {
        $this->get('/products/does-not-exist')->assertNotFound();
    }

    private function createProductsPricedAt500And1000And1500(): void
    {
        Product::factory()->create(['name' => 'Budget Mouse', 'price' => 500]);
        Product::factory()->create(['name' => 'Mid Keyboard', 'price' => 1000]);
        Product::factory()->create(['name' => 'Premium Monitor', 'price' => 1500]);
    }
}
