<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<EventOccurrenceDomainObject>
 */
interface EventOccurrenceRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}
