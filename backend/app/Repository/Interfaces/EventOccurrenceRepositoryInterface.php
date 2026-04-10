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

    /**
     * Acquires a row-level lock on the occurrence (SELECT ... FOR UPDATE) so callers can
     * safely read-then-update without losing concurrent state changes. Must be called
     * inside a database transaction.
     */
    public function findByIdLocked(int $id): ?EventOccurrenceDomainObject;
}
