<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_removes_an_image_and_its_file(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $image = $this->storedImage($product);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->from("/admin/products/{$product->id}/edit")
            ->delete("/admin/products/{$product->id}/images/{$image->id}");

        $response->assertRedirect("/admin/products/{$product->id}/edit");
        $response->assertSessionHas('status', 'Image removed.');
        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_removing_the_primary_image_makes_the_next_image_primary(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $primary = ProductImage::factory()->for($product)->primary()->create(['sort_order' => 1]);
        $third = ProductImage::factory()->for($product)->create(['sort_order' => 3]);
        $second = ProductImage::factory()->for($product)->create(['sort_order' => 2]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/admin/products/{$product->id}/images/{$primary->id}");

        $this->assertTrue($second->refresh()->is_primary);
        $this->assertFalse($third->refresh()->is_primary);
    }

    public function test_returns_404_for_an_image_that_belongs_to_another_product(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $otherImage = $this->storedImage(Product::factory()->create());

        $response = $this->actingAs(User::factory()->admin()->create())
            ->delete("/admin/products/{$product->id}/images/{$otherImage->id}");

        $response->assertNotFound();
        $this->assertModelExists($otherImage);
        Storage::disk('public')->assertExists($otherImage->path);
    }

    public function test_forbids_customers_from_removing_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $image = $this->storedImage($product);

        $response = $this->actingAs(User::factory()->create())
            ->delete("/admin/products/{$product->id}/images/{$image->id}");

        $response->assertForbidden();
        $this->assertModelExists($image);
        Storage::disk('public')->assertExists($image->path);
    }

    private function storedImage(Product $product): ProductImage
    {
        return ProductImage::factory()->for($product)->create([
            'path' => UploadedFile::fake()->image('photo.jpg')->store('products', 'public'),
        ]);
    }
}
