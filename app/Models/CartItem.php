<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cart_id', 'product_id', 'quantity'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Determine whether the line can be bought as it stands: customers can see the product
     * and it has enough stock for the quantity.
     */
    public function isPurchasable(): bool
    {
        return $this->product->isVisibleToCustomers() && $this->quantity <= $this->product->stock;
    }

    /**
     * Get the product's current price times the quantity, formatted like a decimal column.
     */
    public function lineTotal(): string
    {
        return number_format((float) $this->product->price * $this->quantity, 2, '.', '');
    }
}
