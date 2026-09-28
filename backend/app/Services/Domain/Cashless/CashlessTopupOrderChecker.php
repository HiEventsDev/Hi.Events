<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\Generated\CashlessTopupDomainObjectAbstract;
use HiEvents\Repository\Interfaces\CashlessTopupRepositoryInterface;

class CashlessTopupOrderChecker
{
    public function __construct(
        private readonly CashlessTopupRepositoryInterface $topupRepository,
    ) {}

    public function isTopupOrder(int $orderId): bool
    {
        return $this->topupRepository->findFirstWhere([
            CashlessTopupDomainObjectAbstract::ORDER_ID => $orderId,
        ]) !== null;
    }
}
