<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['category_id', 'name', 'slug', 'description', 'price', 'stock', 'status'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stock' => 0,
        'status' => ProductStatus::Active->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the image shown on listings: the one flagged primary, otherwise the first by sort order.
     *
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope a query to products whose name contains the given term.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->whereLike('name', '%'.$term.'%');
    }

    /**
     * Scope a query to the products customers can see: active ones in an active category.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function visibleToCustomers(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->whereHas('category', fn (Builder $category) => $category->active());
    }

    /**
     * Determine whether the product is active.
     */
    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }

    /**
     * Determine whether at least one unit can be bought.
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }
}
