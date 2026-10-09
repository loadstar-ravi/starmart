<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryStatusController extends Controller
{
    /**
     * Activate or deactivate a category.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update(['is_active' => $validated['is_active']]);

        return back()->with(
            'status',
            "Category \"{$category->name}\" ".($category->is_active ? 'activated.' : 'deactivated.'),
        );
    }
}
