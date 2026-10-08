<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\TaxCalculationType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Order\DTO\OrderItemPricingLineDTO;
use HiEvents\Services\Domain\Order\DTO\OrderLineDiscountAllocationDTO;
use HiEvents\Services\Domain\Order\DTO\ProcessedOrderItemDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\DTO\PriceDTO;
use HiEvents\Services\Domain\Product\ProductPriceService;
use HiEvents\Services\Domain\Tax\TaxAndFeeCalculationService;
use Illuminate\Support\Collection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class OrderItemProcessingService
{
    private ?OrganizerConfigurationDomainObject $organizerConfiguration = null;

    private ?EventSettingDomainObject $eventSettings = null;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TaxAndFeeCalculationService $taxCalculationService,
        private readonly ProductPriceService $productPriceService,
        private readonly OrderPlatformFeePassThroughService $platformFeeService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderDiscountAllocationService $orderDiscountAllocationService,
        private readonly SeatedProductLookupService $seatedProductLookup,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
    ) {}

    /**
     * @param  Collection<ProductOrderDetailsDTO>  $productsOrderDetails
     * @return Collection<int, ProcessedOrderItemDTO>
     */
    public function process(
        OrderDomainObject $order,
        Collection $productsOrderDetails,
        EventDomainObject $event,
        ?PromoCodeDomainObject $promoCode,
        bool $allowClientPrices = false,
        bool $applyPlatformFee = true,
    ): Collection {
        if ($applyPlatformFee) {
            $this->loadPlatformFeeConfiguration($event->getId());
        }

        $pricingLines = $this->buildPricingLines($productsOrderDetails, $event, $promoCode, $allowClientPrices);

        if ($promoCode?->isOrderLevelDiscount()) {
            $pricingLines = $this->applyOrderLevelDiscount($pricingLines, $promoCode, $event->getCurrency());
        }

        return $pricingLines->map(fn (OrderItemPricingLineDTO $line) => new ProcessedOrderItemDTO(
            order_item: $this->orderRepository->addOrderItem(
                $this->calculateOrderItemData($line, $order, $event->getCurrency())
            ),
            seat_uids: $line->product_price->seat_uids,
        ));
    }

    /**
     * @param  Collection<ProductOrderDetailsDTO>  $productsOrderDetails
     * @return Collection<int, OrderItemPricingLineDTO>
     */
    private function buildPricingLines(
        Collection $productsOrderDetails,
        EventDomainObject $event,
        ?PromoCodeDomainObject $promoCode,
        bool $allowClientPrices,
    ): Collection {
        $pricingLines = collect();

        foreach ($productsOrderDetails as $productOrderDetail) {
            $product = $this->productRepository
                ->loadRelation(TaxAndFeesDomainObject::class)
                ->loadRelation(ProductPriceDomainObject::class)
                ->findFirstWhere([
                    ProductDomainObjectAbstract::ID => $productOrderDetail->product_id,
                    ProductDomainObjectAbstract::EVENT_ID => $event->getId(),
                ]);

            if ($product === null) {
                throw new ResourceNotFoundException(
                    __('Product with id :id not found', ['id' => $productOrderDetail->product_id])
                );
            }

            $eventOccurrenceId = $productOrderDetail->event_occurrence_id;

            $productOrderDetail->quantities->each(function (OrderProductPriceDTO $productPrice) use ($pricingLines, $promoCode, $product, $event, $eventOccurrenceId, $allowClientPrices) {
                if ($productPrice->quantity === 0) {
                    return;
                }
                $bandKey = $this->bandOfLine($event->getId(), $productPrice);

                $pricingLines->push(new OrderItemPricingLineDTO(
                    product: $product,
                    product_price: $productPrice->band_key === $bandKey
                        ? $productPrice
                        : new OrderProductPriceDTO(
                            quantity: $productPrice->quantity,
                            price_id: $productPrice->price_id,
                            price: $productPrice->price,
                            seat_uids: $productPrice->seat_uids,
                            band_key: $bandKey,
                        ),
                    prices: $this->productPriceService->getPrice(
                        $product,
                        $productPrice,
                        $promoCode,
                        $eventOccurrenceId,
                        $allowClientPrices,
                        $this->bandPriceAdjustment($product->getId(), $bandKey, $event->getCurrency()),
                    ),
                    event_occurrence_id: $eventOccurrenceId,
                ));
            });
        }

        return $pricingLines;
    }

    /**
     * @param  Collection<int, OrderItemPricingLineDTO>  $pricingLines
     * @return Collection<int, OrderItemPricingLineDTO>
     */
    private function applyOrderLevelDiscount(Collection $pricingLines, PromoCodeDomainObject $promoCode, string $currency): Collection
    {
        $allocations = $this->orderDiscountAllocationService->allocate($pricingLines, $promoCode, $currency);

        return $pricingLines
            ->flatMap(static function (OrderItemPricingLineDTO $line, int $index) use ($allocations) {
                $seatOffset = 0;

                return collect($allocations[$index])->map(static function (OrderLineDiscountAllocationDTO $allocation) use ($line, &$seatOffset) {
                    if ($allocation->per_unit_discount <= 0 && $allocation->quantity === $line->product_price->quantity) {
                        return $line;
                    }

                    $seatUids = array_slice($line->product_price->seat_uids, $seatOffset, $allocation->quantity);
                    $seatOffset += $allocation->quantity;

                    return new OrderItemPricingLineDTO(
                        product: $line->product,
                        product_price: new OrderProductPriceDTO(
                            quantity: $allocation->quantity,
                            price_id: $line->product_price->price_id,
                            price: $line->product_price->price,
                            seat_uids: $seatUids,
                            band_key: $line->product_price->band_key,
                        ),
                        prices: $allocation->per_unit_discount > 0
                            ? new PriceDTO(
                                price: Currency::round($line->prices->price - $allocation->per_unit_discount),
                                price_before_discount: $line->prices->price,
                            )
                            : $line->prices,
                        event_occurrence_id: $line->event_occurrence_id,
                    );
                });
            })
            ->values();
    }

    private function loadPlatformFeeConfiguration(int $eventId): void
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: OrganizerConfigurationDomainObject::class,
                        name: 'organizer_configuration',
                    ),
                ],
                name: 'organizer',
            ))
            ->findById($eventId);

        $this->eventSettings = $event->getEventSettings();
        $this->organizerConfiguration = $event->getOrganizer()?->getOrganizerConfiguration();
    }

    private function calculateOrderItemData(
        OrderItemPricingLineDTO $line,
        OrderDomainObject $order,
        string $currency,
    ): array {
        $product = $line->product;
        $productPriceDetails = $line->product_price;
        $eventOccurrenceId = $line->event_occurrence_id;
        $priceWithDiscount = $line->prices->price;
        $priceBeforeDiscount = $line->prices->price_before_discount;

        $itemTotalWithDiscount = $priceWithDiscount * $productPriceDetails->quantity;

        $taxesAndFees = $this->taxCalculationService->calculateTaxAndFeesForProduct(
            product: $product,
            price: $priceWithDiscount,
            quantity: $productPriceDetails->quantity
        );

        $totalTax = $taxesAndFees->taxTotal;
        $totalFee = $taxesAndFees->feeTotal;
        $rollUp = $taxesAndFees->rollUp;

        $platformFee = $this->calculatePlatformFee(
            $itemTotalWithDiscount + $taxesAndFees->feeTotal + $taxesAndFees->taxTotal,
            $productPriceDetails->quantity,
            $currency
        );

        if ($platformFee > 0) {
            $totalFee += $platformFee;
            $rollUp = $this->addPlatformFeeToRollup($rollUp, $platformFee);
        }

        $totalGross = Currency::round($itemTotalWithDiscount + $totalTax + $totalFee);

        return [
            'product_type' => $product->getProductType(),
            'product_id' => $product->getId(),
            'product_price_id' => $productPriceDetails->price_id,
            'quantity' => $productPriceDetails->quantity,
            'price_before_discount' => $priceBeforeDiscount,
            'total_before_additions' => Currency::round($itemTotalWithDiscount),
            'price' => $priceWithDiscount,
            'order_id' => $order->getId(),
            'item_name' => $this->getOrderItemLabel($product, $productPriceDetails->price_id),
            'total_tax' => $totalTax,
            'total_service_fee' => $totalFee,
            'total_gross' => $totalGross,
            'taxes_and_fees_rollup' => $rollUp,
            'event_occurrence_id' => $eventOccurrenceId,
            'band_key' => $productPriceDetails->band_key,
        ];
    }

    private function bandOfLine(int $eventId, OrderProductPriceDTO $productPrice): ?string
    {
        return $productPrice->seat_uids === []
            ? null
            : $this->eventSeatMapLookup->bandOf($eventId, $productPrice->seat_uids[0]);
    }

    private function bandPriceAdjustment(int $productId, ?string $bandKey, string $currency): float
    {
        return Currency::fromMinorUnits(
            $this->seatedProductLookup->priceAdjustmentFor($productId, $bandKey),
            $currency,
        );
    }

    private function calculatePlatformFee(float $total, int $quantity, string $currency): float
    {
        if ($this->organizerConfiguration === null || $this->eventSettings === null) {
            return 0.0;
        }

        return $this->platformFeeService->calculatePlatformFee(
            $this->organizerConfiguration,
            $this->eventSettings,
            $total,
            $quantity,
            $currency,
        );
    }

    private function addPlatformFeeToRollup(array $rollUp, float $platformFee): array
    {
        $rollUp['fees'] ??= [];
        $rollUp['fees'][] = [
            'name' => OrderPlatformFeePassThroughService::getPlatformFeeName(),
            'rate' => $platformFee,
            'type' => TaxCalculationType::FIXED->name,
            'value' => $platformFee,
        ];

        return $rollUp;
    }

    private function getOrderItemLabel(ProductDomainObject $product, int $priceId): string
    {
        if ($product->isTieredType()) {
            return $product->getTitle().' - '.$product->getProductPrices()
                ?->filter(fn ($p) => $p->getId() === $priceId)->first()
                ?->getLabel();
        }

        return $product->getTitle();
    }
}
