<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the storefront home page.
     */
    public function __invoke(CatalogService $catalogService): View
    {
        return view('home', [
            'categories' => $catalogService->activeCategories(),
            'newArrivals' => $catalogService->newArrivals(),
        ]);
    }
}
