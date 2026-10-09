<?php

namespace App\Services;

use App\Exceptions\CategoryHasProductsException;
use App\Models\Category;

class CategoryService
{
    public function __construct(private SlugGenerator $slugGenerator) {}

    /**
     * Create a category, generating its slug from the name when none is given.
     *
     * @param  array{name: string, slug?: string|null, description?: string|null, is_active: bool|int|string}  $attributes
     */
    public function create(array $attributes): Category
    {
        $attributes['slug'] ??= $this->slugGenerator->generate(Category::class, $attributes['name']);

        return Category::create($attributes);
    }

    /**
     * Update a category, regenerating its slug from the name when the slug is cleared.
     *
     * @param  array{name: string, slug?: string|null, description?: string|null, is_active: bool|int|string}  $attributes
     */
    public function update(Category $category, array $attributes): Category
    {
        $attributes['slug'] ??= $this->slugGenerator->generate(Category::class, $attributes['name'], $category);

        $category->update($attributes);

        return $category;
    }

    /**
     * Delete a category that no product belongs to.
     *
     * @throws CategoryHasProductsException
     */
    public function delete(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new CategoryHasProductsException($category);
        }

        $category->delete();
    }
}
