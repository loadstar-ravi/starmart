<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * List the products, optionally filtered by search term, category and status.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
        ]);

        $search = $filters['search'] ?? null;
        $categoryId = $filters['category'] ?? null;
        $status = $filters['status'] ?? null;

        $products = Product::query()
            ->with(['category', 'primaryImage'])
            ->when($search !== null, fn (Builder $query) => $query->search($search))
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ProductStatus::cases(),
            'filters' => ['search' => $search, 'category' => $categoryId, 'status' => $status],
        ]);
    }

    /**
     * Show the form for creating a product.
     */
    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product,
            'categories' => $this->categoryOptions(),
            'statuses' => ProductStatus::cases(),
        ]);
    }

    /**
     * Store a new product with its uploaded images.
     */
    public function store(ProductRequest $request, ProductService $productService): RedirectResponse
    {
        $product = $productService->create(
            $request->safe()->except('images'),
            $request->validated('images') ?? [],
        );

        return redirect()
            ->route('admin.products.index')
            ->with('status', "Product \"{$product->name}\" created.");
    }

    /**
     * Show the form for editing a product.
     */
    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product->load('images'),
            'categories' => $this->categoryOptions(),
            'statuses' => ProductStatus::cases(),
        ]);
    }

    /**
     * Update a product and add any newly uploaded images.
     */
    public function update(ProductRequest $request, Product $product, ProductService $productService): RedirectResponse
    {
        $productService->update(
            $product,
            $request->safe()->except('images'),
            $request->validated('images') ?? [],
        );

        return redirect()
            ->route('admin.products.index')
            ->with('status', "Product \"{$product->name}\" updated.");
    }

    /**
     * Delete a product and its images.
     */
    public function destroy(Product $product, ProductService $productService): RedirectResponse
    {
        $productService->delete($product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', "Product \"{$product->name}\" deleted.");
    }

    /**
     * Get the categories a product can be assigned to.
     *
     * @return Collection<int, Category>
     */
    private function categoryOptions(): Collection
    {
        return Category::query()->orderBy('name')->get(['id', 'name', 'is_active']);
    }
}
