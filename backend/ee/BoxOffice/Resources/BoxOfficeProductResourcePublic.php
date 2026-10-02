<?php

namespace HiEvents\Enterprise\BoxOffice\Resources;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Resources\Tax\TaxAndFeeResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDomainObject
 */
class BoxOfficeProductResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            /** @var 'PAID'|'FREE'|'TIERED' */
            'type' => $this->getType(),
            /** @var 'TICKET'|'GENERAL' */
            'product_type' => $this->getProductType(),
            'description' => $this->getDescription(),
            'max_per_order' => $this->getMaxPerOrder(),
            'product_category_id' => $this->getProductCategoryId(),
            'is_hidden' => $this->getIsHidden(),
            'is_hidden_without_promo_code' => $this->getIsHiddenWithoutPromoCode(),
            'is_addon_only' => $this->getIsAddonOnly(),
            'prices' => ($this->getProductPrices() ?? collect())->map(fn (ProductPriceDomainObject $price) => [
                'id' => $price->getId(),
                'label' => $price->getLabel(),
                'price' => $price->getPrice(),
                'price_including_taxes_and_fees' => $price->getPriceIncludingTaxAndServiceFee(),
                'tax_total' => $price->getTaxTotal(),
                'fee_total' => $price->getFeeTotal(),
                'is_available' => (bool) $price->isAvailable(),
                'quantity_remaining' => $price->getQuantityAvailable(),
                /** @var array<string, float>|null */
                'band_prices' => $price->getBandPrices() === null ? null : (object) $price->getBandPrices(),
                /** @var 'BEFORE_SALE_START'|'AFTER_SALE_END'|null */
                'off_sale_reason' => $this->offSaleReason($price),
            ])->values()->all(),
            'taxes' => $this->when(
                (bool) $this->getTaxAndFees(),
                fn () => TaxAndFeeResource::collection($this->getTaxAndFees())
            ),
        ];
    }

    private function offSaleReason(ProductPriceDomainObject $price): ?string
    {
        $beforeStart = $this->isTieredType() ? $price->isBeforeSaleStartDate() : $this->isBeforeSaleStartDate();
        $afterEnd = $this->isTieredType() ? $price->isAfterSaleEndDate() : $this->isAfterSaleEndDate();

        return match (true) {
            $beforeStart => 'BEFORE_SALE_START',
            $afterEnd => 'AFTER_SALE_END',
            default => null,
        };
    }
}
