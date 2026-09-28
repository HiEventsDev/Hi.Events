<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\SalesPoint;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\Generated\CashlessSalesPointDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\CashlessSalesPointRepositoryInterface;

class GetCashlessSalesPointHandler
{
    public function __construct(
        private readonly CashlessSalesPointRepositoryInterface $salesPointRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $salesPointId): CashlessSalesPointDomainObject
    {
        $salesPoint = $this->salesPointRepository
            ->loadRelation(ProductDomainObject::class)
            ->findFirstWhere([
                CashlessSalesPointDomainObjectAbstract::ID => $salesPointId,
                CashlessSalesPointDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

        if ($salesPoint === null) {
            throw new ResourceNotFoundException(__('This sales point could not be found.'));
        }

        return $salesPoint;
    }
}
