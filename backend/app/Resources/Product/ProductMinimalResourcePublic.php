<?php

namespace HiEvents\Resources\Product;

use HiEvents\DomainObjects\ProductDomainObject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDomainObject
 */
class ProductMinimalResourcePublic extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'type' => $this->getType(),
            'event_id' => $this->getEventId(),
            'prices' => $this->when(
                (bool)$this->getProductPrices(),
                fn() => ProductPriceResourcePublic::collection($this->getProductPrices()),
            ),
            'product_category_id' => $this->getProductCategoryId(),
        ];
    }
}
