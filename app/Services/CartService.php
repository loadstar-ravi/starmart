<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Changes a user's cart within the shop's rules: only products customers can see,
 * and never more of one than is in stock.
 */
class CartService
{
    /**
     * Get the user's cart with everything needed to display it.
     *
     * A user who has not added anything yet gets an empty cart that is not saved.
     */
    public function cartFor(User $user): Cart
    {
        return $user->cart()
            ->with(['items.product.category', 'items.product.primaryImage'])
            ->firstOrNew();
    }

    /**
     * Add a product to the user's cart, raising the quantity when it is already there.
     *
     * The cart row is locked so two simultaneous adds cannot both insert the same line.
     *
     * @throws CartException
     */
    public function addProduct(User $user, int $productId, int $quantity): CartItem
    {
        $product = $this->findBuyableProduct($productId);
        $cart = $user->cart()->firstOrCreate();

        return DB::transaction(function () use ($cart, $product, $quantity) {
            Cart::query()->lockForUpdate()->find($cart->id);

            $item = $cart->items()->firstOrNew(['product_id' => $product->id], ['quantity' => 0]);

            if ($item->quantity + $quantity > $product->stock) {
                throw CartException::insufficientStock($product, $item->quantity);
            }

            $item->quantity += $quantity;
            $item->save();

            return $item->setRelation('product', $product);
        });
    }

    /**
     * Set the quantity of a line in the user's cart.
     *
     * @throws CartException
     * @throws ModelNotFoundException<CartItem>
     */
    public function updateQuantity(User $user, int $itemId, int $quantity): CartItem
    {
        $item = $this->findItem($user, $itemId);
        $product = $this->findBuyableProduct($item->product_id);

        if ($quantity > $product->stock) {
            throw CartException::insufficientStock($product);
        }

        $item->update(['quantity' => $quantity]);

        return $item;
    }

    /**
     * Remove a line from the user's cart.
     *
     * @throws ModelNotFoundException<CartItem>
     */
    public function removeItem(User $user, int $itemId): void
    {
        $this->findItem($user, $itemId)->delete();
    }

    /**
     * Find a line by its id, looking only in the user's own cart.
     *
     * @throws ModelNotFoundException<CartItem>
     */
    private function findItem(User $user, int $itemId): CartItem
    {
        return $user->cartItems()->findOrFail($itemId);
    }

    /**
     * @throws CartException
     */
    private function findBuyableProduct(int $productId): Product
    {
        return Product::query()->visibleToCustomers()->find($productId)
            ?? throw CartException::productUnavailable();
    }
}
