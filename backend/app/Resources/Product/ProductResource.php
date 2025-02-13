<?php

namespace HiEvents\Resources\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Resources\Tax\TaxAndFeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDomainObject
 */
class ProductResource extends JsonResource
{
    public const DEFAULT_MIN_PRODUCTS = 1;

    public const DEFAULT_MAX_PRODUCTS = 10;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'type' => $this->getType(),
            'product_type' => $this->getProductType(),
            'order' => $this->getOrder(),
            'description' => $this->getDescription(),
            $this->mergeWhen(
                condition: $this->getType() !== ProductPriceType::TIERED->name,
                value: fn() => [
                    'price' => $this->getPrice(),
                ]
            ),
            'max_per_order' => $this->getMaxPerOrder() ?? self::DEFAULT_MAX_PRODUCTS,
            'min_per_order' => $this->getMinPerOrder() ?? self::DEFAULT_MIN_PRODUCTS,
            'quantity_sold' => $this->getQuantitySold(),
            'sale_start_date' => $this->getSaleStartDate(),
            'sale_end_date' => $this->getSaleEndDate(),
            'event_id' => $this->getEventId(),
            'initial_quantity_available' => $this->getInitialQuantityAvailable(),
            'hide_before_sale_start_date' => $this->getHideBeforeSaleStartDate(),
            'hide_after_sale_end_date' => $this->getHideAfterSaleEndDate(),
            'start_collapsed' => $this->getStartCollapsed(),
            'show_quantity_remaining' => $this->getShowQuantityRemaining(),
            'hide_when_sold_out' => $this->getHideWhenSoldOut(),
            'is_hidden_without_promo_code' => $this->getIsHiddenWithoutPromoCode(),
            'is_hidden' => $this->getIsHidden(),
            'is_before_sale_start_date' => $this->isBeforeSaleStartDate(),
            'is_after_sale_end_date' => $this->isAfterSaleEndDate(),
            'is_available' => $this->isAvailable(),
            $this->mergeWhen((bool)$this->getProductPrices(), fn() => [
                'is_sold_out' => $this->isSoldOut(),
            ]),
            'taxes_and_fees' => $this->when(
                (bool)$this->getTaxAndFees(),
                fn() => TaxAndFeeResource::collection($this->getTaxAndFees())
            ),
            'prices' => $this->when(
                (bool)$this->getProductPrices(),
                fn() => ProductPriceResource::collection($this->getProductPrices())
            ),
            'product_category_id' => $this->getProductCategoryId(),
        ];
    }
}
