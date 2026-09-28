<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\SalesPoint;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\Services\Application\Handlers\Cashless\DTO\UpsertCashlessSalesPointDTO;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointService;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use Throwable;

class CreateCashlessSalesPointHandler
{
    public function __construct(
        private readonly CashlessSalesPointService $salesPointService,
    ) {}

    /**
     * @throws UnrecognizedProductIdException
     * @throws Throwable
     */
    public function handle(UpsertCashlessSalesPointDTO $salesPointData): CashlessSalesPointDomainObject
    {
        return $this->salesPointService->create($salesPointData);
    }
}
