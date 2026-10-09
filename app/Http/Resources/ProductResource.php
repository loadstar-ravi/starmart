<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'in_stock' => $this->isInStock(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'image_url' => $this->whenLoaded('primaryImage', fn () => $this->primaryImage->url),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
