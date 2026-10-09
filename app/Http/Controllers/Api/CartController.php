<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Show the cart of the user the token belongs to.
     */
    public function show(Request $request, CartService $cartService): CartResource
    {
        return new CartResource($cartService->cartFor($request->user()));
    }
}
