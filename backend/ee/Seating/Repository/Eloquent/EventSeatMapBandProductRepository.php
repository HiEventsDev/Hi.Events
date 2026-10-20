<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Repository\Eloquent;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapBandProductRepositoryInterface;
use HiEvents\Models\EventSeatMapBandProduct;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<EventSeatMapBandProductDomainObject>
 */
class EventSeatMapBandProductRepository extends BaseRepository implements EventSeatMapBandProductRepositoryInterface
{
    protected function getModel(): string
    {
        return EventSeatMapBandProduct::class;
    }

    public function getDomainObject(): string
    {
        return EventSeatMapBandProductDomainObject::class;
    }
}
