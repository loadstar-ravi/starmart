<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductImage
 */
class ProductImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, url: string, is_primary: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'is_primary' => $this->is_primary,
        ];
    }
}
