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

    public function getStatsByEventId(int $eventId): WaitlistStatsDTO;

    public function getProductStatsByEventId(int $eventId): Collection;

    public function getMaxPosition(int $productPriceId): int;

    /**
     * @return Collection<int, WaitlistEntryDomainObject>
     */
    public function getNextWaitingEntries(int $productPriceId, int $limit): Collection;

    public function lockForProductPrice(int $productPriceId): void;

    public function findByIdLocked(int $id): ?WaitlistEntryDomainObject;
}
