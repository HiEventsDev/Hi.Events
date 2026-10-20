<?php

namespace HiEvents\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<EventOccurrenceDomainObject>
 */
interface EventOccurrenceRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findByIdLocked(int $id): ?EventOccurrenceDomainObject;

    /**
     * @return int[] ids of the event's dates that are not cancelled and end after $endedAfter (default now)
     */
    public function findUpcomingIdsForEvent(int $eventId, ?CarbonInterface $endedAfter = null): array;
}
