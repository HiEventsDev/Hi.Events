<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\DTO\CashlessSalesPointStatsDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CashlessSalesPointDomainObject>
 */
interface CashlessSalesPointRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function syncProducts(int $salesPointId, array $productIds): void;

    /**
     * @param  array<int>  $salesPointIds
     * @return Collection<CashlessSalesPointStatsDTO>
     */
    public function getStatsByIds(array $salesPointIds): Collection;
}
