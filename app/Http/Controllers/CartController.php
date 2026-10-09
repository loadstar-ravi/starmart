<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    /**
     * Show the signed-in user's cart.
     */
    public function show(Request $request, CartService $cartService): View
    {
        return view('cart.show', [
            'cart' => $cartService->cartFor($request->user()),
        ]);
    }
}
