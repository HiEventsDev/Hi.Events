<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Repository\Interfaces;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;

/**
 * @extends RepositoryInterface<EventSeatMapDomainObject>
 */
interface EventSeatMapRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  int[]  $eventIds
     * @return int[] the ids of the events that have a seat map
     */
    public function findEventIdsWithSeatMaps(array $eventIds): array;

    public function existsForUpcomingEvent(?int $accountId): bool;
}
