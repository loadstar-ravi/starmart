<?php

namespace Tests\Feature\Models;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_product_keeps_its_order_items_with_the_purchase_snapshot(): void
    {
        $product = Product::factory()->create();
        $orderItem = OrderItem::factory()->for($product)->create([
            'product_name' => 'Gaming Laptop',
            'unit_price' => 50000,
        ]);

        $product->delete();

        $orderItem->refresh();
        $this->assertNull($orderItem->product_id);
        $this->assertSame('Gaming Laptop', $orderItem->product_name);
        $this->assertSame('50000.00', $orderItem->unit_price);
    }

    public function test_deleting_a_product_removes_its_images_and_cart_items(): void
    {
        $product = Product::factory()->create();
        $image = ProductImage::factory()->for($product)->create();
        $cartItem = CartItem::factory()->for($product)->create();

        $product->delete();

        $this->assertModelMissing($image);
        $this->assertModelMissing($cartItem);
    }

    public function test_a_category_that_still_has_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create();

        try {
            $category->delete();
            $this->fail('Deleting a category with products should violate the foreign key.');
        } catch (QueryException) {
            $this->assertModelExists($category);
        }
    }

    public function test_primary_image_is_the_flagged_image_rather_than_the_first_uploaded(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->create();
        $flagged = ProductImage::factory()->for($product)->primary()->create();

        $this->assertTrue($product->primaryImage->is($flagged));
    }

    public function test_primary_image_falls_back_to_the_lowest_sort_order_when_none_is_flagged(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->create(['sort_order' => 2]);
        $first = ProductImage::factory()->for($product)->create(['sort_order' => 1]);

        $this->assertTrue($product->primaryImage->is($first));
    }
}
