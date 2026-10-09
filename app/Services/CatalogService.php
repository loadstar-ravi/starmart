<?php

namespace App\Services;

use App\Enums\ProductSort;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Reads the catalog the way customers see it: active products in active categories only.
 */
class CatalogService
{
    public const int DEFAULT_PER_PAGE = 12;

    /**
     * Paginate the products customers can see, narrowed and ordered by the given filters.
     *
     * @param  array{search?: string|null, category?: string|null, min_price?: int|float|string|null, max_price?: int|float|string|null, sort?: string|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateProducts(array $filters, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $sort = ProductSort::tryFrom($filters['sort'] ?? '') ?? ProductSort::Newest;

        $query = Product::query()
            ->visibleToCustomers()
            ->with(['category', 'primaryImage'])
            ->when(
                isset($filters['search']),
                fn (Builder $query) => $query->search($filters['search']),
            )
            ->when(
                isset($filters['category']),
                fn (Builder $query) => $query->whereRelation('category', 'slug', $filters['category']),
            )
            ->when(
                isset($filters['min_price']),
                fn (Builder $query) => $query->where('price', '>=', (float) $filters['min_price']),
            )
            ->when(
                isset($filters['max_price']),
                fn (Builder $query) => $query->where('price', '<=', (float) $filters['max_price']),
            );

        return $this->applySort($query, $sort)->paginate($perPage);
    }

    /**
     * Get the newest products that are in stock.
     *
     * @return Collection<int, Product>
     */
    public function newArrivals(int $limit = 8): Collection
    {
        $query = Product::query()
            ->visibleToCustomers()
            ->with(['category', 'primaryImage'])
            ->where('stock', '>', 0);

        return $this->applySort($query, ProductSort::Newest)->limit($limit)->get();
    }

    /**
     * Get the categories customers can browse.
     *
     * @return Collection<int, Category>
     */
    public function activeCategories(): Collection
    {
        return Category::query()->active()->orderBy('name')->get(['id', 'name', 'slug']);
    }

    /**
     * Find a product customers can see by its id.
     *
     * @throws ModelNotFoundException<Product>
     */
    public function findVisibleProduct(int $id): Product
    {
        return $this->visibleProductWithDetails()->whereKey($id)->firstOrFail();
    }

    /**
     * Find a product customers can see by its slug.
     *
     * @throws ModelNotFoundException<Product>
     */
    public function findVisibleProductBySlug(string $slug): Product
    {
        return $this->visibleProductWithDetails()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @return Builder<Product>
     */
    private function visibleProductWithDetails(): Builder
    {
        return Product::query()->visibleToCustomers()->with(['category', 'images', 'primaryImage']);
    }

    /**
     * Order the query, breaking ties by id so pages never overlap.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applySort(Builder $query, ProductSort $sort): Builder
    {
        return match ($sort) {
            ProductSort::Newest => $query->orderByDesc('created_at')->orderByDesc('id'),
            ProductSort::PriceLowToHigh => $query->orderBy('price')->orderBy('id'),
            ProductSort::PriceHighToLow => $query->orderByDesc('price')->orderByDesc('id'),
        };
    }
}
