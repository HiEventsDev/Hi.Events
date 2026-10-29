<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\Generated\EventSeatMapBandProductDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapBandProductRepositoryInterface;
use Illuminate\Support\Collection;

class SeatedProductLookupService
{
    /**
     * @var array<int, Collection<int, EventSeatMapBandProductDomainObject>>
     */
    private array $linksByProduct = [];

    public function __construct(
        private readonly EventSeatMapBandProductRepositoryInterface $bandProductRepository,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
    ) {}

    public function isSeated(int $productId): bool
    {
        return $this->bandKeysFor($productId) !== [];
    }

    /**
     * @return string[]
     */
    public function bandKeysFor(int $productId): array
    {
        return $this->linksFor($productId)
            ->map(fn (EventSeatMapBandProductDomainObject $link) => $link->getBandKey())
            ->all();
    }

    /**
     * @return int the signed adjustment in minor units, 0 when the product is not sold in that band
     */
    public function priceAdjustmentFor(int $productId, ?string $bandKey): int
    {
        if ($bandKey === null) {
            return 0;
        }

        return $this->linksFor($productId)
            ->first(fn (EventSeatMapBandProductDomainObject $link) => $link->getBandKey() === $bandKey)
            ?->getPriceAdjustment() ?? 0;
    }

    /**
     * @return Collection<int, EventSeatMapBandProductDomainObject>
     */
    public function linksForEvent(int $eventId): Collection
    {
        return $this->eventSeatMapLookup->findSummaryForEvent($eventId)?->getEventSeatMapBandProducts() ?? collect();
    }

    /**
     * @return array<int, string[]> sorted band keys per seated product id
     */
    public function bandKeysByProduct(int $eventId): array
    {
        return $this->linksForEvent($eventId)
            ->groupBy(fn (EventSeatMapBandProductDomainObject $link) => $link->getProductId())
            ->map(fn (Collection $productLinks) => $productLinks
                ->map(fn (EventSeatMapBandProductDomainObject $link) => $link->getBandKey())
                ->unique()
                ->sort()
                ->values()
                ->all())
            ->all();
    }

    public function forget(): void
    {
        $this->linksByProduct = [];
    }

    /**
     * @return Collection<int, EventSeatMapBandProductDomainObject>
     */
    private function linksFor(int $productId): Collection
    {
        return $this->linksByProduct[$productId] ??= $this->bandProductRepository
            ->findWhere([EventSeatMapBandProductDomainObjectAbstract::PRODUCT_ID => $productId]);
    }
}
