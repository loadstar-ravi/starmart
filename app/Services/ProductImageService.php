<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductImageService
{
    /**
     * Store the uploaded files and attach them to the product.
     *
     * The first image a product receives becomes its primary image. Files already written
     * are removed again when anything fails, so the disk never keeps orphaned uploads.
     *
     * @param  array<int, UploadedFile>  $files
     *
     * @throws Throwable
     */
    public function attach(Product $product, array $files): void
    {
        if ($files === []) {
            return;
        }

        $storedPaths = [];

        try {
            DB::transaction(function () use ($product, $files, &$storedPaths) {
                $hasPrimaryImage = $product->images()->where('is_primary', true)->exists();
                $sortOrder = (int) $product->images()->reorder()->max('sort_order');

                foreach ($files as $file) {
                    $path = $file->store(ProductImage::DIRECTORY, ProductImage::DISK);

                    if ($path === false) {
                        throw new RuntimeException('The product image could not be stored.');
                    }

                    $storedPaths[] = $path;

                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => ! $hasPrimaryImage,
                        'sort_order' => ++$sortOrder,
                    ]);

                    $hasPrimaryImage = true;
                }
            });
        } catch (Throwable $exception) {
            Storage::disk(ProductImage::DISK)->delete($storedPaths);

            throw $exception;
        }
    }

    /**
     * Remove an image and its file, promoting the next image when the primary one is removed.
     */
    public function remove(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            $image->delete();

            if ($image->is_primary) {
                $image->product->images()->first()?->update(['is_primary' => true]);
            }
        });

        Storage::disk(ProductImage::DISK)->delete($image->path);
    }
}
