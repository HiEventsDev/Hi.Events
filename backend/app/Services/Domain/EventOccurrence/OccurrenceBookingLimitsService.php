<?php

namespace HiEvents\Services\Domain\EventOccurrence;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\EventOccurrence\DTO\OccurrenceBookingLimitsDTO;
use HiEvents\Services\Domain\EventOccurrence\DTO\OccurrenceTierAllocationDTO;
use Illuminate\Support\Collection;

class OccurrenceBookingLimitsService
{
    public function __construct(
        private readonly ProductPriceOccurrenceOverrideRepositoryInterface $overrideRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * @param  Collection<EventOccurrenceDomainObject>  $occurrences
     */
    public function attachTo(Collection $occurrences, int $eventId): void
    {
        if ($occurrences->isEmpty()) {
            return;
        }

        $products = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findWhere(
                where: [ProductDomainObjectAbstract::EVENT_ID => $eventId],
                orderAndDirections: [
                    new OrderAndDirection(ProductDomainObjectAbstract::ORDER),
                    new OrderAndDirection(ProductDomainObjectAbstract::ID),
                ],
            );

        $limits = $this->forOccurrences($occurrences, $products);

        $occurrences->each(
            fn (EventOccurrenceDomainObject $occurrence) => $occurrence->setBookingLimits($limits[$occurrence->getId()] ?? null)
        );
    }

    /**
     * @param  Collection<EventOccurrenceDomainObject>  $occurrences
     * @param  Collection<ProductDomainObject>  $products  products with prices loaded
     * @return array<int, OccurrenceBookingLimitsDTO> keyed by occurrence id
     */
    private function forOccurrences(Collection $occurrences, Collection $products): array
    {
        $occurrenceIds = $occurrences->map(fn (EventOccurrenceDomainObject $occurrence) => $occurrence->getId())->all();

        $overrides = $occurrenceIds === []
            ? collect()
            : $this->overrideRepository->findWhereIn('event_occurrence_id', $occurrenceIds);

        $overridesByOccurrence = $overrides->groupBy(
            fn (ProductPriceOccurrenceOverrideDomainObject $override) => $override->getEventOccurrenceId()
        );

        $limits = [];
        foreach ($occurrences as $occurrence) {
            $limits[$occurrence->getId()] = $this->forOccurrence(
                $occurrence,
                $products,
                $overridesByOccurrence->get($occurrence->getId(), collect())
                    ->keyBy(fn (ProductPriceOccurrenceOverrideDomainObject $override) => $override->getProductPriceId()),
            );
        }

        return $limits;
    }

    private function forOccurrence(
        EventOccurrenceDomainObject $occurrence,
        Collection $products,
        Collection $overridesByPrice,
    ): OccurrenceBookingLimitsDTO {
        $allocations = [];
        $allocationTotal = 0;
        $uncapped = false;

        /** @var ProductDomainObject $product */
        foreach ($products as $product) {
            if ($product->getProductType() !== ProductType::TICKET->name) {
                continue;
            }

            $prices = ($product->getProductPrices() ?? collect())
                ->sortBy(fn (ProductPriceDomainObject $price) => $price->getOrder());

            /** @var ProductPriceDomainObject $price */
            foreach ($prices as $price) {
                $quantity = $this->allocationForDate($price, $overridesByPrice->get($price->getId()));

                if ($quantity === null) {
                    $uncapped = true;
                } else {
                    $allocationTotal += $quantity;
                }

                $allocations[] = new OccurrenceTierAllocationDTO(
                    product_price_id: $price->getId(),
                    product_title: $product->getTitle(),
                    price_label: $price->getLabel(),
                    quantity: $quantity,
                    applies_to: $price->getQuantityAppliesTo(),
                );
            }
        }

        $allocationTotal = $uncapped || $allocations === [] ? null : $allocationTotal;
        $capacity = $occurrence->getCapacity();
        $limits = array_filter([$capacity, $allocationTotal], fn (?int $value) => $value !== null);

        return new OccurrenceBookingLimitsDTO(
            capacity: $capacity,
            allocation_total: $allocationTotal,
            sellable: $limits === [] ? null : min($limits),
            allocations: $allocations,
        );
    }

    private function allocationForDate(ProductPriceDomainObject $price, ?ProductPriceOccurrenceOverrideDomainObject $override): ?int
    {
        if (! $price->isQuantityPerOccurrence()) {
            return null;
        }

        return $override?->getQuantityAvailable() ?? $price->getInitialQuantityAvailable();
    }
}
