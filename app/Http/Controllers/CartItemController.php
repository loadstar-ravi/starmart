<?php

namespace App\Http\Controllers;

use App\Exceptions\CartException;
use App\Http\Requests\CartItemRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartItemController extends Controller
{
    /**
     * Add a product to the cart.
     */
    public function store(CartItemRequest $request, CartService $cartService): RedirectResponse
    {
        try {
            $item = $cartService->addProduct(
                $request->user(),
                $request->integer('product_id'),
                $request->integer('quantity'),
            );
        } catch (CartException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', "\"{$item->product->name}\" added to your cart.");
    }

    /**
     * Set the quantity of a line in the cart.
     *
     * A JSON request gets the cart re-rendered, which the page swaps in without reloading.
     */
    public function update(Request $request, int $item, CartService $cartService): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'quantity' => CartItemRequest::QUANTITY_RULES,
        ]);

        try {
            $cartService->updateQuantity($request->user(), $item, (int) $validated['quantity']);
        } catch (CartException $exception) {
            return $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY)
                : redirect()->route('cart.show')->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            $cart = $cartService->cartFor($request->user());

            return response()->json([
                'html' => view('cart._contents', ['cart' => $cart])->render(),
                'total_quantity' => $cart->totalQuantity(),
            ]);
        }

        return redirect()->route('cart.show')->with('status', 'Cart updated.');
    }

    /**
     * Remove a line from the cart.
     */
    public function destroy(Request $request, int $item, CartService $cartService): RedirectResponse
    {
        $cartService->removeItem($request->user(), $item);

        return redirect()->route('cart.show')->with('status', 'Item removed from your cart.');
    }
}
