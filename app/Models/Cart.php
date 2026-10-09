<?php

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /**
     * Get the number of units in the cart across all of its lines.
     */
    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Get what the lines that can be bought right now cost together, formatted like a decimal column.
     */
    public function total(): string
    {
        $total = $this->items
            ->filter(fn (CartItem $item) => $item->isPurchasable())
            ->sum(fn (CartItem $item) => (float) $item->lineTotal());

        return number_format($total, 2, '.', '');
    }

    /**
     * Determine whether the cart holds a line that is left out of the total.
     */
    public function hasUnpurchasableItems(): bool
    {
        return $this->items->contains(fn (CartItem $item) => ! $item->isPurchasable());
    }
}
