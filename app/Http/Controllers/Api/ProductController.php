<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductFilterRequest;
use App\Http\Resources\ProductResource;
use App\Services\CatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * List the products customers can buy, filtered, sorted and paginated.
     */
    public function index(ProductFilterRequest $request, CatalogService $catalogService): AnonymousResourceCollection
    {
        $products = $catalogService
            ->paginateProducts($request->validated(), $request->perPage())
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * Show a single product.
     */
    public function show(int $product, CatalogService $catalogService): ProductResource
    {
        return new ProductResource($catalogService->findVisibleProduct($product));
    }
}
