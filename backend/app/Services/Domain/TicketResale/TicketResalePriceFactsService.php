<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Services\Domain\Order\OrderPlatformFeePassThroughService;
use HiEvents\Services\Domain\Product\ProductFilterService;
use HiEvents\Services\Domain\Product\ProductPriceService;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResalePriceFactsDTO;
use HiEvents\Values\MoneyValue;

class TicketResalePriceFactsService
{
    public function __construct(
        private readonly ProductPriceService $productPriceService,
        private readonly TaxAndFeeCalculationService $taxAndFeeCalculationService,
        private readonly OrderPlatformFeePassThroughService $platformFeeService,
        private readonly ProductFilterService $productFilterService,
    ) {}

    public function forTicket(
        AttendeeDomainObject $attendee,
        ProductDomainObject $product,
        OrderDomainObject $order,
        EventDomainObject $event,
    ): TicketResalePriceFactsDTO {
        $sellerItem = $this->cheapestSellerItem($attendee, $order);
        $price = $this->hasFixedPrice($product) ? $product->getPriceById($attendee->getProductPriceId()) : null;
        $currentUnitPrice = $price === null
            ? 0.0
            : $this->productPriceService->getIndividualPrice($product, $price, null, $attendee->getEventOccurrenceId());

        return new TicketResalePriceFactsDTO(
            is_on_sale: $price !== null
                && $product->isOnSale()
                && $price->isOnSale()
                && ! $this->isLockedBehindEarlierTier($product, $price, $attendee->getEventOccurrenceId()),
            seller_unit_price: $sellerItem?->getPrice() ?? 0.0,
            seller_unit_gross: $sellerItem === null ? 0 : $this->unitGross($sellerItem, $order->getCurrency()),
            current_unit_price: $currentUnitPrice,
            current_unit_gross: $currentUnitPrice > 0 ? $this->currentUnitGross($product, $currentUnitPrice, $event) : 0,
        );
    }

    private function hasFixedPrice(ProductDomainObject $product): bool
    {
        return $product->getType() === ProductPriceType::PAID->name || $product->isTieredType();
    }

    private function cheapestSellerItem(AttendeeDomainObject $attendee, OrderDomainObject $order): ?OrderItemDomainObject
    {
        return $order->getOrderItems()
            ?->filter(fn (OrderItemDomainObject $item) => $item->getProductPriceId() === $attendee->getProductPriceId()
                && $item->getEventOccurrenceId() === $attendee->getEventOccurrenceId()
                && $item->getQuantity() > 0)
            ->sortBy(fn (OrderItemDomainObject $item) => $this->unitGross($item, $order->getCurrency()))
            ->first();
    }

    private function unitGross(OrderItemDomainObject $item, string $currency): int
    {
        return intdiv(
            MoneyValue::fromFloat((float) $item->getTotalGross(), $currency)->toMinorUnit(),
            $item->getQuantity(),
        );
    }

    private function currentUnitGross(ProductDomainObject $product, float $unitPrice, EventDomainObject $event): int
    {
        $taxesAndFees = $this->taxAndFeeCalculationService->calculateTaxAndFeesForProduct($product, $unitPrice);
        $totalBeforePlatformFee = $unitPrice + $taxesAndFees->feeTotal + $taxesAndFees->taxTotal;
        $organizerConfiguration = $event->getOrganizer()?->getOrganizerConfiguration();
        $eventSettings = $event->getEventSettings();

        $platformFee = $organizerConfiguration === null || $eventSettings === null
            ? 0.0
            : $this->platformFeeService->calculatePlatformFee(
                $organizerConfiguration,
                $eventSettings,
                $totalBeforePlatformFee,
                1,
                $event->getCurrency(),
            );

        return MoneyValue::fromFloat(
            Currency::round($totalBeforePlatformFee + $platformFee),
            $event->getCurrency(),
        )->toMinorUnit();
    }

    private function isLockedBehindEarlierTier(ProductDomainObject $product, ProductPriceDomainObject $price, ?int $eventOccurrenceId): bool
    {
        if (! $product->isTieredType() || ! $product->getSequentialTierReleaseEnabled()) {
            return false;
        }

        $purchasablePrice = $this->productFilterService
            ->filterProducts(
                products: collect([$product]),
                hideSoldOutProducts: false,
                eventOccurrenceId: $eventOccurrenceId,
                includeResaleStock: true,
            )
            ->first()
            ?->getPriceById($price->getId());

        return $purchasablePrice === null || $purchasablePrice->isLockedBehindEarlierTier();
    }
}
