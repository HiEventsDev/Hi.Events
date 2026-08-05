<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\WaitlistStatsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<WaitlistEntryDomainObject>
 */
interface WaitlistEntryRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function getStatsByEventId(int $eventId, ?int $eventOccurrenceId = null): WaitlistStatsDTO;

    public function getProductStatsByEventId(int $eventId, ?int $eventOccurrenceId = null): Collection;

    public function getMaxPosition(int $productPriceId, ?int $eventOccurrenceId = null): int;

    /**
     * @return Collection<int, WaitlistEntryDomainObject>
     */
    public function getNextWaitingEntries(int $productPriceId, ?int $limit = null, ?int $eventOccurrenceId = null): Collection;

    public function lockForProductPrice(int $productPriceId, ?int $eventOccurrenceId = null): void;

    public function findByIdLocked(int $id): ?WaitlistEntryDomainObject;
}
