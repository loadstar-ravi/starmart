<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductStockController extends Controller
{
    /**
     * Set the stock level of a product.
     */
    public function update(Request $request, Product $product, ProductService $productService): RedirectResponse
    {
        $validated = $request->validate([
            'stock' => ProductRequest::STOCK_RULES,
        ]);

        $productService->updateStock($product, (int) $validated['stock']);

        return back()->with('status', "Stock for \"{$product->name}\" set to {$product->stock}.");
    }
}
