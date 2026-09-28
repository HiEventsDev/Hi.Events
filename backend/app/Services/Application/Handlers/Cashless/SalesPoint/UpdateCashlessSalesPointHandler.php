<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\SalesPoint;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\Generated\CashlessSalesPointDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\CashlessSalesPointRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\DTO\UpsertCashlessSalesPointDTO;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Throwable;

class UpdateCashlessSalesPointHandler
{
    public function __construct(
        private readonly CashlessSalesPointRepositoryInterface $salesPointRepository,
        private readonly CashlessSalesPointService $salesPointService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws UnrecognizedProductIdException
     * @throws Throwable
     */
    public function handle(UpsertCashlessSalesPointDTO $salesPointData): CashlessSalesPointDomainObject
    {
        $existing = $this->salesPointRepository->findFirstWhere([
            CashlessSalesPointDomainObjectAbstract::ID => $salesPointData->id,
            CashlessSalesPointDomainObjectAbstract::EVENT_ID => $salesPointData->event_id,
        ]);

        if ($existing === null) {
            throw new ResourceNotFoundException(__('This sales point could not be found.'));
        }

        return $this->salesPointService->update($existing, $salesPointData);
    }
}
