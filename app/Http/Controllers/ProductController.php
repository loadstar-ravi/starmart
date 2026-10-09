<?php

namespace App\Http\Controllers;

use App\Enums\ProductSort;
use App\Http\Requests\ProductFilterRequest;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * List the products customers can buy, filtered, sorted and paginated.
     *
     * A JSON request gets only the rendered results, which the page swaps in without reloading.
     * Both variants share one URL, so they vary on Accept and the fragment is never stored;
     * otherwise the browser could show the cached JSON when the customer navigates back.
     */
    public function index(ProductFilterRequest $request, CatalogService $catalogService): Response|JsonResponse
    {
        $filters = $request->validated();

        $products = $catalogService->paginateProducts($filters)->withQueryString();

        if ($request->expectsJson()) {
            return response()
                ->json([
                    'html' => view('products._results', ['products' => $products])->render(),
                    'total' => $products->total(),
                ])
                ->header('Vary', 'Accept')
                ->header('Cache-Control', 'no-store');
        }

        return response()
            ->view('products.index', [
                'products' => $products,
                'categories' => $catalogService->activeCategories(),
                'sorts' => ProductSort::cases(),
                'filters' => $filters,
            ])
            ->header('Vary', 'Accept');
    }

    /**
     * Show a single product.
     */
    public function show(string $slug, CatalogService $catalogService): View
    {
        $product = $catalogService->findVisibleProductBySlug($slug);

        return view('products.show', [
            'product' => $product,
            'mainImage' => $product->images->firstWhere('is_primary', true) ?? $product->images->first(),
        ]);
    }
}
