<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;

/**
 * Thrown when a cart change, or an order for the cart, breaks a shopping rule.
 * The message is written for the customer.
 */
class CartException extends Exception
{
    /**
     * There is nothing in the cart to order.
     */
    public static function emptyCart(): self
    {
        return new self('Your cart is empty.');
    }

    /**
     * A product in the cart has been hidden from customers since it was added.
     */
    public static function productNoLongerAvailable(Product $product): self
    {
        return new self("\"{$product->name}\" is no longer available.");
    }

    /**
     * The product does not exist or is hidden from customers.
     */
    public static function productUnavailable(): self
    {
        return new self('This product is not available.');
    }

    /**
     * The cart would hold more of the product than is in stock.
     */
    public static function insufficientStock(Product $product, int $quantityInCart = 0): self
    {
        if (! $product->isInStock()) {
            return new self("\"{$product->name}\" is out of stock.");
        }

        if ($quantityInCart > 0) {
            return new self(
                "\"{$product->name}\" has only {$product->stock} in stock, and your cart already has {$quantityInCart}.",
            );
        }

        return new self("\"{$product->name}\" has only {$product->stock} in stock.");
    }
}
