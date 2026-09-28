<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\SalesPoint;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\DTO\CashlessSalesPointStatsDTO;
use HiEvents\Repository\Interfaces\CashlessSalesPointRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCashlessSalesPointsHandler
{
    public function __construct(
        private readonly CashlessSalesPointRepositoryInterface $salesPointRepository,
    ) {}

    public function handle(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $paginator = $this->salesPointRepository
            ->loadRelation(ProductDomainObject::class)
            ->findByEventId($eventId, $params);

        $stats = $this->salesPointRepository
            ->getStatsByIds($paginator->getCollection()
                ->map(fn (CashlessSalesPointDomainObject $salesPoint) => $salesPoint->getId())
                ->toArray())
            ->keyBy(fn (CashlessSalesPointStatsDTO $stat) => $stat->salesPointId);

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (CashlessSalesPointDomainObject $salesPoint) => $salesPoint
                    ->setSalesTotal($stats->get($salesPoint->getId())?->salesTotal)
                    ->setTransactionCount($stats->get($salesPoint->getId())?->transactionCount)
            )
        );

        return $paginator;
    }
}
