<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Every change answers with the whole cart, so clients always hold the current lines and totals.
 */
class CartItemController extends Controller
{
    /**
     * Add a product to the cart.
     */
    public function store(CartItemRequest $request, CartService $cartService): JsonResponse
    {
        try {
            $cartService->addProduct(
                $request->user(),
                $request->integer('product_id'),
                $request->integer('quantity'),
            );
        } catch (CartException $exception) {
            return $this->rejected($exception);
        }

        return (new CartResource($cartService->cartFor($request->user())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Set the quantity of a line in the cart.
     */
    public function update(Request $request, int $item, CartService $cartService): CartResource|JsonResponse
    {
        $validated = $request->validate([
            'quantity' => CartItemRequest::QUANTITY_RULES,
        ]);

        try {
            $cartService->updateQuantity($request->user(), $item, (int) $validated['quantity']);
        } catch (CartException $exception) {
            return $this->rejected($exception);
        }

        return new CartResource($cartService->cartFor($request->user()));
    }

    /**
     * Remove a line from the cart.
     */
    public function destroy(Request $request, int $item, CartService $cartService): CartResource
    {
        $cartService->removeItem($request->user(), $item);

        return new CartResource($cartService->cartFor($request->user()));
    }

    /**
     * Answer a change the shop's rules do not allow.
     */
    private function rejected(CartException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
