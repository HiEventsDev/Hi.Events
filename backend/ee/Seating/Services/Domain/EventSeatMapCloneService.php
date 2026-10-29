<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\Generated\EventSeatMapBandProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSeatMapDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapBandProductRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;

class EventSeatMapCloneService
{
    public function __construct(
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
        private readonly EventSeatMapBandProductRepositoryInterface $bandProductRepository,
    ) {}

    /**
     * @param  array<int, int>  $oldProductToNewProductMap
     */
    public function clone(int $sourceEventId, int $newEventId, array $oldProductToNewProductMap): void
    {
        $source = $this->eventSeatMapRepository
            ->loadRelation(EventSeatMapBandProductDomainObject::class)
            ->findFirstWhere([EventSeatMapDomainObjectAbstract::EVENT_ID => $sourceEventId]);

        if ($source === null) {
            return;
        }

        $clone = $this->eventSeatMapRepository->create([
            EventSeatMapDomainObjectAbstract::EVENT_ID => $newEventId,
            EventSeatMapDomainObjectAbstract::SEAT_MAP_ID => $source->getSeatMapId(),
            EventSeatMapDomainObjectAbstract::SOURCE_VERSION => $source->getSourceVersion(),
            EventSeatMapDomainObjectAbstract::LAYOUT => $source->getLayout(),
            EventSeatMapDomainObjectAbstract::PREVENT_ORPHAN_SEATS => $source->getPreventOrphanSeats(),
            EventSeatMapDomainObjectAbstract::MAX_SEATS_PER_ORDER => $source->getMaxSeatsPerOrder(),
            EventSeatMapDomainObjectAbstract::ALLOW_SEAT_CHANGE => $source->getAllowSeatChange(),
        ]);

        foreach ($source->getEventSeatMapBandProducts() as $link) {
            $newProductId = $oldProductToNewProductMap[$link->getProductId()] ?? null;
            if ($newProductId === null) {
                continue;
            }

            $this->bandProductRepository->create([
                EventSeatMapBandProductDomainObjectAbstract::EVENT_SEAT_MAP_ID => $clone->getId(),
                EventSeatMapBandProductDomainObjectAbstract::BAND_KEY => $link->getBandKey(),
                EventSeatMapBandProductDomainObjectAbstract::PRODUCT_ID => $newProductId,
                EventSeatMapBandProductDomainObjectAbstract::PRICE_ADJUSTMENT => $link->getPriceAdjustment(),
            ]);
        }
    }
}
