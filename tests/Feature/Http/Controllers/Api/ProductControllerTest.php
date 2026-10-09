<?php

namespace Tests\Feature\Http\Controllers\Api;

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

    public function test_lists_products_without_a_token(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $product = Product::factory()->for($category)->create([
            'name' => 'Gaming Laptop',
            'slug' => 'gaming-laptop',
            'description' => 'A fast laptop.',
            'price' => 54990.50,
            'stock' => 12,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('meta.per_page', 12);
        $response->assertJsonPath('data.0', [
            'id' => $product->id,
            'name' => 'Gaming Laptop',
            'slug' => 'gaming-laptop',
            'description' => 'A fast laptop.',
            'price' => '54990.50',
            'stock' => 12,
            'in_stock' => true,
            'category' => ['id' => $category->id, 'name' => 'Electronics', 'slug' => 'electronics'],
            'image_url' => null,
            'created_at' => $product->created_at->toIso8601String(),
        ]);
    }

    public function test_leaves_out_products_customers_cannot_see(): void
    {
        Product::factory()->create(['name' => 'Gaming Laptop']);
        Product::factory()->inactive()->create(['name' => 'Retired Phone']);
        Product::factory()->for(Category::factory()->inactive())->create(['name' => 'Hidden Tablet']);

        $response = $this->getJson('/api/products');

        $this->assertSame(['Gaming Laptop'], $response->json('data.*.name'));
    }

    public function test_marks_a_product_without_stock_as_not_in_stock(): void
    {
        Product::factory()->outOfStock()->create();

        $response = $this->getJson('/api/products');

        $response->assertJsonPath('data.0.stock', 0);
        $response->assertJsonPath('data.0.in_stock', false);
    }

    public function test_filters_by_search_term_category_and_price_range(): void
    {
        $electronics = Category::factory()->create(['slug' => 'electronics']);
        Product::factory()->for($electronics)->create(['name' => 'Gaming Laptop', 'price' => 50000]);
        Product::factory()->for($electronics)->create(['name' => 'Budget Laptop', 'price' => 9000]);
        Product::factory()->for($electronics)->create(['name' => 'Wireless Mouse', 'price' => 20000]);
        Product::factory()->create(['name' => 'Laptop Sleeve', 'price' => 15000]);

        $response = $this->getJson('/api/products?search=laptop&category=electronics&min_price=10000&max_price=100000');

        $response->assertOk();
        $this->assertSame(['Gaming Laptop'], $response->json('data.*.name'));
    }

    public function test_sorts_by_price_from_high_to_low(): void
    {
        Product::factory()->create(['name' => 'Budget Mouse', 'price' => 500]);
        Product::factory()->create(['name' => 'Premium Monitor', 'price' => 1500]);
        Product::factory()->create(['name' => 'Mid Keyboard', 'price' => 1000]);

        $response = $this->getJson('/api/products?sort=price_desc');

        $this->assertSame(['Premium Monitor', 'Mid Keyboard', 'Budget Mouse'], $response->json('data.*.name'));
    }

    public function test_per_page_sets_the_page_size_and_is_kept_in_the_page_links(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.last_page', 2);
        $this->assertStringContainsString('per_page=2', $response->json('links.next'));
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
            'unknown sort order' => ['sort=popular', 'sort', 'The selected sort is invalid.'],
            'unknown category' => ['category=missing', 'category', 'The selected category is invalid.'],
            'page size above the limit' => ['per_page=49', 'per_page', 'The per page field must not be greater than 48.'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_returns_422_for_an_invalid_filter(string $query, string $field, string $message): void
    {
        $response = $this->getJson("/api/products?{$query}");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([$field => $message]);
    }

    public function test_shows_a_product_with_its_category_and_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['name' => 'Gaming Laptop']);
        $side = ProductImage::factory()->for($product)->create(['sort_order' => 2]);
        $front = ProductImage::factory()->for($product)->primary()->create(['sort_order' => 1]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $product->id);
        $response->assertJsonPath('data.name', 'Gaming Laptop');
        $response->assertJsonPath('data.category.id', $product->category_id);
        $response->assertJsonPath('data.image_url', $front->url);
        $response->assertJsonPath('data.images', [
            ['id' => $front->id, 'url' => $front->url, 'is_primary' => true],
            ['id' => $side->id, 'url' => $side->url, 'is_primary' => false],
        ]);
    }

    public function test_returns_404_for_an_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'The requested resource was not found.']);
    }

    public function test_returns_404_for_a_product_in_an_inactive_category(): void
    {
        $product = Product::factory()->for(Category::factory()->inactive())->create();

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_returns_404_for_an_unknown_product(): void
    {
        $response = $this->getJson('/api/products/999999');

        $response->assertNotFound();
        $response->assertExactJson(['message' => 'The requested resource was not found.']);
    }

    public function test_returns_404_for_an_id_that_is_not_a_number(): void
    {
        $this->getJson('/api/products/gaming-laptop')->assertNotFound();
    }
}
