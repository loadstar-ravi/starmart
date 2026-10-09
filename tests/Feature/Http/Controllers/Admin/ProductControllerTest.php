<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_guests_to_the_admin_login_form(): void
    {
        $response = $this->get('/admin/products');

        $response->assertRedirectToRoute('admin.login');
    }

    public function test_forbids_customers_from_listing_products(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get('/admin/products');

        $response->assertForbidden();
    }

    public function test_forbids_customers_from_creating_products(): void
    {
        $customer = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($customer)->post('/admin/products', $this->validPayload($category));

        $response->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_lists_products_newest_first_with_their_category(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);
        Product::factory()->for($category)->create(['name' => 'Wireless Mouse']);
        Product::factory()->for($category)->create(['name' => 'Gaming Laptop']);

        $response = $this->actingAs($this->admin())->get('/admin/products');

        $response->assertOk();
        $response->assertSeeInOrder(['Gaming Laptop', 'Wireless Mouse']);
        $response->assertSee('Electronics');
    }

    public function test_search_lists_only_products_whose_name_contains_the_term(): void
    {
        Product::factory()->create(['name' => 'Gaming Laptop']);
        Product::factory()->create(['name' => 'Wireless Mouse']);

        $response = $this->actingAs($this->admin())->get('/admin/products?search=laptop');

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertDontSee('Wireless Mouse');
    }

    public function test_category_filter_lists_only_products_of_that_category(): void
    {
        $electronics = Category::factory()->create();
        Product::factory()->for($electronics)->create(['name' => 'Gaming Laptop']);
        Product::factory()->create(['name' => 'Cotton Shirt']);

        $response = $this->actingAs($this->admin())->get("/admin/products?category={$electronics->id}");

        $response->assertOk();
        $response->assertSee('Gaming Laptop');
        $response->assertDontSee('Cotton Shirt');
    }

    public function test_status_filter_lists_only_products_with_that_status(): void
    {
        Product::factory()->create(['name' => 'Gaming Laptop']);
        Product::factory()->inactive()->create(['name' => 'Cotton Shirt']);

        $response = $this->actingAs($this->admin())->get('/admin/products?status=inactive');

        $response->assertOk();
        $response->assertSee('Cotton Shirt');
        $response->assertDontSee('Gaming Laptop');
    }

    public function test_rejects_a_status_filter_that_is_not_a_product_status(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/products?status=archived');

        $response->assertSessionHasErrors(['status' => 'The selected status is invalid.']);
    }

    public function test_escapes_product_names_in_the_list(): void
    {
        Product::factory()->create(['name' => "<script>alert('xss')</script>"]);

        $response = $this->actingAs($this->admin())->get('/admin/products');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_renders_the_create_form_with_the_categories_to_choose_from(): void
    {
        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->inactive()->create(['name' => 'Books']);

        $response = $this->actingAs($this->admin())->get('/admin/products/create');

        $response->assertOk();
        $response->assertSeeInOrder(['Books (inactive)', 'Electronics']);
    }

    public function test_renders_the_edit_form_with_the_current_values(): void
    {
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'price' => 54990.50]);

        $response = $this->actingAs($this->admin())->get("/admin/products/{$product->id}/edit");

        $response->assertOk();
        $response->assertSee('value="Gaming Laptop"', false);
        $response->assertSee('value="54990.50"', false);
    }

    public function test_valid_payload_creates_a_product_with_a_slug_generated_from_the_name(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())->post('/admin/products', $this->validPayload($category));

        $response->assertRedirectToRoute('admin.products.index');
        $response->assertSessionHas('status', 'Product "Gaming Laptop" created.');

        $product = Product::sole();
        $this->assertSame('Gaming Laptop', $product->name);
        $this->assertSame('gaming-laptop', $product->slug);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('A fast laptop.', $product->description);
        $this->assertSame('54990.50', $product->price);
        $this->assertSame(12, $product->stock);
        $this->assertSame(ProductStatus::Active, $product->status);
    }

    public function test_generated_slug_gets_a_numeric_suffix_when_another_product_uses_it(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['name' => 'Gaming Laptop', 'slug' => 'gaming-laptop']);

        $this->actingAs($this->admin())->post('/admin/products', $this->validPayload($category));

        $this->assertDatabaseHas('products', ['category_id' => $category->id, 'slug' => 'gaming-laptop-2']);
    }

    public function test_stores_uploaded_images_and_marks_the_first_as_primary(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())->post('/admin/products', $this->validPayload($category, [
            'images' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.png'),
            ],
        ]));

        $response->assertSessionHasNoErrors();

        $images = Product::sole()->images;
        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        Storage::disk('public')->assertExists($images->pluck('path')->all());
    }

    public function test_empty_payload_returns_required_errors_and_creates_no_product(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/products', []);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'category_id' => 'The category field is required.',
            'price' => 'The price field is required.',
            'stock' => 'The stock field is required.',
            'status' => 'The status field is required.',
        ]);
        $this->assertDatabaseCount('products', 0);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidFields(): array
    {
        return [
            'price of zero' => ['price', '0', 'The price field must be greater than 0.'],
            'negative price' => ['price', '-5', 'The price field must be greater than 0.'],
            'price that is not a number' => ['price', 'abc', 'The price field must be a number.'],
            'price with three decimals' => ['price', '10.999', 'The price field must have 0-2 decimal places.'],
            'negative stock' => ['stock', '-1', 'The stock field must be at least 0.'],
            'fractional stock' => ['stock', '1.5', 'The stock field must be an integer.'],
            'category that does not exist' => ['category_id', '999999', 'The selected category is invalid.'],
            'status that does not exist' => ['status', 'archived', 'The selected status is invalid.'],
            'name longer than 150 characters' => [
                'name',
                str_repeat('a', 151),
                'The name field must not be greater than 150 characters.',
            ],
            'slug with spaces' => [
                'slug',
                'Not A Slug',
                'The slug may only contain lowercase letters, numbers and hyphens.',
            ],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_rejects_an_invalid_field_and_creates_no_product(string $field, string $value, string $message): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post('/admin/products', $this->validPayload($category, [$field => $value]));

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_rejects_a_slug_that_another_product_uses(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['slug' => 'gaming-laptop']);

        $response = $this->actingAs($this->admin())
            ->post('/admin/products', $this->validPayload($category, ['slug' => 'gaming-laptop']));

        $response->assertSessionHasErrors(['slug' => 'The slug has already been taken.']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_rejects_an_upload_that_is_not_an_image(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())->post('/admin/products', $this->validPayload($category, [
            'images' => [UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')],
        ]));

        $response->assertSessionHasErrors(['images.0' => 'The image field must be an image.']);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_rejects_an_image_larger_than_two_megabytes(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())->post('/admin/products', $this->validPayload($category, [
            'images' => [UploadedFile::fake()->image('huge.jpg')->size(2049)],
        ]));

        $response->assertSessionHasErrors(['images.0' => 'The image field must not be greater than 2048 kilobytes.']);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_rejects_more_than_five_images_in_one_upload(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $images = array_map(fn (int $number) => UploadedFile::fake()->image("photo-{$number}.jpg"), range(1, 6));

        $response = $this->actingAs($this->admin())
            ->post('/admin/products', $this->validPayload($category, ['images' => $images]));

        $response->assertSessionHasErrors(['images' => 'You may upload at most 5 images at a time.']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_valid_payload_updates_the_product(): void
    {
        $product = Product::factory()->create(['name' => 'Gaming Laptop', 'slug' => 'gaming-laptop']);
        $newCategory = Category::factory()->create();

        $response = $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name' => 'Gaming Laptop Pro',
            'slug' => 'gaming-laptop',
            'category_id' => $newCategory->id,
            'description' => 'Now even faster.',
            'price' => '64990',
            'stock' => '3',
            'status' => 'inactive',
        ]);

        $response->assertRedirectToRoute('admin.products.index');
        $response->assertSessionHas('status', 'Product "Gaming Laptop Pro" updated.');

        $product->refresh();
        $this->assertSame('Gaming Laptop Pro', $product->name);
        $this->assertSame('gaming-laptop', $product->slug);
        $this->assertSame($newCategory->id, $product->category_id);
        $this->assertSame('Now even faster.', $product->description);
        $this->assertSame('64990.00', $product->price);
        $this->assertSame(3, $product->stock);
        $this->assertSame(ProductStatus::Inactive, $product->status);
    }

    public function test_update_adds_uploaded_images_without_replacing_the_primary_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $existing = ProductImage::factory()->for($product)->primary()->create(['sort_order' => 1]);

        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", $this->validPayload($product->category, [
            'images' => [UploadedFile::fake()->image('side.webp')],
        ]));

        $images = $product->images()->get();
        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is($existing));
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        Storage::disk('public')->assertExists($images[1]->path);
    }

    public function test_update_rejects_an_invalid_price_and_leaves_the_product_unchanged(): void
    {
        $product = Product::factory()->create(['price' => 500]);

        $response = $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", $this->validPayload($product->category, ['price' => '0']));

        $response->assertSessionHasErrors(['price' => 'The price field must be greater than 0.']);
        $this->assertSame('500.00', $product->refresh()->price);
    }

    public function test_deletes_a_product_and_its_image_files(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create(['name' => 'Gaming Laptop']);
        $path = UploadedFile::fake()->image('front.jpg')->store('products', 'public');
        $image = ProductImage::factory()->for($product)->create(['path' => $path]);

        $response = $this->actingAs($this->admin())->delete("/admin/products/{$product->id}");

        $response->assertRedirectToRoute('admin.products.index');
        $response->assertSessionHas('status', 'Product "Gaming Laptop" deleted.');
        $this->assertModelMissing($product);
        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($path);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Category $category, array $overrides = []): array
    {
        return [
            'name' => 'Gaming Laptop',
            'category_id' => $category->id,
            'description' => 'A fast laptop.',
            'price' => '54990.50',
            'stock' => '12',
            'status' => 'active',
            ...$overrides,
        ];
    }
}
