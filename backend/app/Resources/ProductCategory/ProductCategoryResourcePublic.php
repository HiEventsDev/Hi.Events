<?php

namespace HiEvents\Resources\ProductCategory;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Resources\Product\ProductResourcePublic;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductCategoryDomainObject
 */
class ProductCategoryResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'is_hidden' => $this->getIsHidden(),
            'order' => $this->getOrder(),
            'no_products_message' => $this->getNoProductsMessage(),
            $this->mergeWhen((bool)$this->getProducts(), fn() => [
                'products' => ProductResourcePublic::collection($this->getProducts()),
            ]),
        ];
    }
}
