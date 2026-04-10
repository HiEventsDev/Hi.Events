<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\DTO\PriceDTO;

class ProductPriceService
{
    public function __construct(
        private readonly ProductPriceOccurrenceOverrideRepositoryInterface $priceOverrideRepository,
    )
    {
    }

    public function getIndividualPrice(
        ProductDomainObject      $product,
        ProductPriceDomainObject $price,
        ?PromoCodeDomainObject   $promoCode,
        ?int                     $eventOccurrenceId = null,
    ): float
    {
        return $this->getPrice($product, new OrderProductPriceDTO(
            quantity: 1,
            price_id: $price->getId(),
        ), $promoCode, $eventOccurrenceId)->price;
    }

    public function getPrice(
        ProductDomainObject    $product,
        OrderProductPriceDTO   $productOrderDetail,
        ?PromoCodeDomainObject $promoCode,
        ?int                   $eventOccurrenceId = null,
    ): PriceDTO
    {
        $price = $this->determineProductPrice($product, $productOrderDetail, $eventOccurrenceId);

        if ($product->getType() === ProductPriceType::FREE->name) {
            return new PriceDTO(0.00);
        }

        if ($product->getType() === ProductPriceType::DONATION->name) {
            return new PriceDTO($price);
        }

        if (!$promoCode || !$promoCode->appliesToProduct($product)) {
            return new PriceDTO($price);
        }

        if ($promoCode->getDiscountType() === PromoCodeDiscountTypeEnum::NONE->name) {
            return new PriceDTO($price);
        }

        if ($promoCode->isFixedDiscount()) {
            $discountPrice = Currency::round($price - $promoCode->getDiscount());
        } elseif ($promoCode->isPercentageDiscount()) {
            $discountPrice = Currency::round(
                $price - ($price * ($promoCode->getDiscount() / 100))
            );
        } else {
            $discountPrice = $price;
        }

        return new PriceDTO(
            price: max(0, $discountPrice),
            price_before_discount: $price
        );
    }

    private function determineProductPrice(ProductDomainObject $product, OrderProductPriceDTO $productOrderDetails, ?int $eventOccurrenceId = null): float
    {
        if ($eventOccurrenceId !== null) {
            $override = $this->priceOverrideRepository->findFirstWhere([
                'event_occurrence_id' => $eventOccurrenceId,
                'product_price_id' => $productOrderDetails->price_id,
            ]);

            if ($override !== null) {
                return (float) $override->getPrice();
            }
        }

        return match ($product->getType()) {
            ProductPriceType::DONATION->name => max($product->getPrice(), $productOrderDetails->price),
            ProductPriceType::PAID->name => $product->getPrice(),
            ProductPriceType::FREE->name => 0.00,
            ProductPriceType::TIERED->name => $product->getPriceById($productOrderDetails->price_id)?->getPrice()
        };
    }
}
