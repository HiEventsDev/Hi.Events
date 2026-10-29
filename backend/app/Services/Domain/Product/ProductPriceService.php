<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\DTO\PriceDTO;

class ProductPriceService
{
    /**
     * @var array<string, float|null>
     */
    private array $overridePrices = [];

    public function __construct(
        private readonly ProductPriceOccurrenceOverrideRepositoryInterface $priceOverrideRepository,
    ) {}

    public function getIndividualPrice(
        ProductDomainObject $product,
        ProductPriceDomainObject $price,
        ?PromoCodeDomainObject $promoCode,
        ?int $eventOccurrenceId = null,
    ): float {
        return $this->getPrice($product, new OrderProductPriceDTO(
            quantity: 1,
            price_id: $price->getId(),
        ), $promoCode, $eventOccurrenceId)->price;
    }

    public function getPrice(
        ProductDomainObject $product,
        OrderProductPriceDTO $productOrderDetail,
        ?PromoCodeDomainObject $promoCode,
        ?int $eventOccurrenceId = null,
        bool $allowClientPrice = false,
        float $bandPriceAdjustment = 0.0,
    ): PriceDTO {
        $price = $this->determineProductPrice($product, $productOrderDetail, $eventOccurrenceId);

        if ($allowClientPrice && $productOrderDetail->price !== null && ! $product->isDonationType()) {
            return new PriceDTO(
                price: Currency::round($productOrderDetail->price),
                price_before_discount: $price,
            );
        }

        $price = $this->applyBandAdjustment($price, $bandPriceAdjustment);

        if ($product->getType() === ProductPriceType::FREE->name) {
            return new PriceDTO(0.00);
        }

        if ($product->getType() === ProductPriceType::DONATION->name) {
            return new PriceDTO($price);
        }

        if (! $promoCode || ! $promoCode->appliesToProduct($product)) {
            return new PriceDTO($price);
        }

        if ($promoCode->getDiscountType() === PromoCodeDiscountTypeEnum::NONE->name) {
            return new PriceDTO($price);
        }

        if ($promoCode->isOrderLevelDiscount()) {
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

    public function getDonationMinimumPrice(ProductDomainObject $product, int $priceId, ?int $eventOccurrenceId): float
    {
        return $this->getOverridePrice($priceId, $eventOccurrenceId) ?? $product->getPrice();
    }

    private function determineProductPrice(ProductDomainObject $product, OrderProductPriceDTO $productOrderDetails, ?int $eventOccurrenceId = null): float
    {
        if ($product->getType() === ProductPriceType::DONATION->name) {
            return max(
                $this->getDonationMinimumPrice($product, $productOrderDetails->price_id, $eventOccurrenceId),
                $productOrderDetails->price,
            );
        }

        $overridePrice = $this->getOverridePrice($productOrderDetails->price_id, $eventOccurrenceId);

        if ($overridePrice !== null) {
            return $overridePrice;
        }

        return match ($product->getType()) {
            ProductPriceType::PAID->name => $product->getPrice(),
            ProductPriceType::FREE->name => 0.00,
            ProductPriceType::TIERED->name => $product->getPriceById($productOrderDetails->price_id)?->getPrice()
        };
    }

    private function getOverridePrice(int $priceId, ?int $eventOccurrenceId): ?float
    {
        if ($eventOccurrenceId === null) {
            return null;
        }

        $key = $priceId.':'.$eventOccurrenceId;

        if (! array_key_exists($key, $this->overridePrices)) {
            $this->overridePrices[$key] = $this->priceOverrideRepository->findFirstWhere([
                'event_occurrence_id' => $eventOccurrenceId,
                'product_price_id' => $priceId,
            ])?->getPrice();
        }

        return $this->overridePrices[$key];
    }

    private function applyBandAdjustment(float $price, float $adjustment): float
    {
        if ($adjustment === 0.0) {
            return $price;
        }

        return max(0.0, Currency::round($price + $adjustment));
    }
}
