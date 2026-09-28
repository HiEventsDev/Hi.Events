<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\SalesPoint;

use HiEvents\DomainObjects\Generated\CashlessSalesPointDomainObjectAbstract;
use HiEvents\Repository\Interfaces\CashlessSalesPointRepositoryInterface;

class DeleteCashlessSalesPointHandler
{
    public function __construct(
        private readonly CashlessSalesPointRepositoryInterface $salesPointRepository,
    ) {}

    public function handle(int $eventId, int $salesPointId): void
    {
        $this->salesPointRepository->deleteWhere([
            CashlessSalesPointDomainObjectAbstract::ID => $salesPointId,
            CashlessSalesPointDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }
}
