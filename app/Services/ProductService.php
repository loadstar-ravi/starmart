<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        private SlugGenerator $slugGenerator,
        private ProductImageService $productImageService,
    ) {}

    /**
     * Create a product with its images, generating the slug from the name when none is given.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $images
     */
    public function create(array $attributes, array $images = []): Product
    {
        return DB::transaction(function () use ($attributes, $images) {
            $attributes['slug'] ??= $this->slugGenerator->generate(Product::class, $attributes['name']);

            $product = Product::create($attributes);

            $this->productImageService->attach($product, $images);

            return $product;
        });
    }

    /**
     * Update a product and add any newly uploaded images to it.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $images
     */
    public function update(Product $product, array $attributes, array $images = []): Product
    {
        return DB::transaction(function () use ($product, $attributes, $images) {
            $attributes['slug'] ??= $this->slugGenerator->generate(Product::class, $attributes['name'], $product);

            $product->update($attributes);

            $this->productImageService->attach($product, $images);

            return $product;
        });
    }

    /**
     * Set the stock level of a product.
     */
    public function updateStock(Product $product, int $stock): void
    {
        $previousStock = $product->stock;

        $product->update(['stock' => $stock]);

        Log::info('Product stock updated.', [
            'product_id' => $product->id,
            'previous_stock' => $previousStock,
            'stock' => $stock,
        ]);
    }

    /**
     * Delete a product together with its image files.
     */
    public function delete(Product $product): void
    {
        $imagePaths = $product->images()->pluck('path')->all();

        $product->delete();

        Storage::disk(ProductImage::DISK)->delete($imagePaths);
    }
}
