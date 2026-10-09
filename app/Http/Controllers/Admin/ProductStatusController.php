<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductStatusController extends Controller
{
    /**
     * Activate or deactivate a product.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ]);

        $product->update(['status' => $validated['status']]);

        return back()->with(
            'status',
            "Product \"{$product->name}\" ".($product->isActive() ? 'activated.' : 'deactivated.'),
        );
    }
}
