<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CategoryHasProductsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * List the categories, optionally filtered by a search term.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $filters['search'] ?? null;

        $categories = Category::query()
            ->when($search !== null, fn (Builder $query) => $query->search($search))
            ->withCount('products')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a category.
     */
    public function create(): View
    {
        return view('admin.categories.create', ['category' => new Category]);
    }

    /**
     * Store a new category.
     */
    public function store(CategoryRequest $request, CategoryService $categoryService): RedirectResponse
    {
        $category = $categoryService->create($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Category \"{$category->name}\" created.");
    }

    /**
     * Show the form for editing a category.
     */
    public function edit(Category $category): View
    {
        return view('admin.categories.edit', ['category' => $category]);
    }

    /**
     * Update a category.
     */
    public function update(CategoryRequest $request, Category $category, CategoryService $categoryService): RedirectResponse
    {
        $categoryService->update($category, $request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Category \"{$category->name}\" updated.");
    }

    /**
     * Delete a category, unless products still belong to it.
     */
    public function destroy(Category $category, CategoryService $categoryService): RedirectResponse
    {
        try {
            $categoryService->delete($category);
        } catch (CategoryHasProductsException) {
            return back()->with(
                'error',
                "Category \"{$category->name}\" still has products. Move or delete them before deleting the category.",
            );
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Category \"{$category->name}\" deleted.");
    }
}
