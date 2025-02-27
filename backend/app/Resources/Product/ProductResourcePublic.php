<?php

namespace HiEvents\Resources\Product;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Resources\Tax\TaxAndFeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDomainObject
 */
class ProductResourcePublic extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'type' => $this->getType(),
            'product_type' => $this->getProductType(),
            'description' => $this->getDescription(),
            'max_per_order' => $this->getMaxPerOrder(),
            'min_per_order' => $this->getMinPerOrder(),
            'sale_start_date' => $this->getSaleStartDate(),
            'sale_end_date' => $this->getSaleEndDate(),
            'event_id' => $this->getEventId(),
            'is_before_sale_start_date' => $this->isBeforeSaleStartDate(),
            'is_after_sale_end_date' => $this->isAfterSaleEndDate(),
            'start_collapsed' => $this->getStartCollapsed(),
            $this->mergeWhen($this->getShowQuantityRemaining(), fn() => [
                'quantity_available' => $this->getQuantityAvailable(),
            ]),
            'price' => $this->when(
                $this->getProductPrices() && !$this->isTieredType(),
                fn() => $this->getPrice(),
            ),
            'prices' => $this->when(
                (bool)$this->getProductPrices(),
                fn() => ProductPriceResourcePublic::collectionWithAdditionalData($this->getProductPrices(), [
                    ProductPriceResourcePublic::SHOW_QUANTITY_AVAILABLE => $this->getShowQuantityRemaining(),
                ]),
            ),
            'taxes' => $this->when(
                (bool)$this->getTaxAndFees(),
                fn() => TaxAndFeeResource::collection($this->getTaxAndFees())
            ),
            $this->mergeWhen((bool)$this->getProductPrices(), fn() => [
                'is_available' => $this->isAvailable(),
                'is_sold_out' => $this->isSoldOut(),
            ]),
            'product_category_id' => $this->getProductCategoryId(),
        ];
    }
}
