<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\RedirectResponse;

class ProductImageController extends Controller
{
    /**
     * Remove an image from a product.
     */
    public function destroy(Product $product, ProductImage $image, ProductImageService $productImageService): RedirectResponse
    {
        $productImageService->remove($image);

        return back()->with('status', 'Image removed.');
    }
}
