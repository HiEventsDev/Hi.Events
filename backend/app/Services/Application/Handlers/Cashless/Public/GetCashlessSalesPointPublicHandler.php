<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Public;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;

class GetCashlessSalesPointPublicHandler
{
    public function __construct(
        private readonly CashlessSalesPointAccessService $salesPointAccessService,
    ) {}

    /**
     * @throws CashlessSalesPointAccessException
     */
    public function handle(string $salesPointShortId, ?string $sessionToken): CashlessSalesPointDomainObject
    {
        try {
            return $this->salesPointAccessService->resolveAuthorised($salesPointShortId, $sessionToken);
        } catch (CashlessSalesPointAccessException $exception) {
            $salesPoint = $this->salesPointAccessService->resolveUnauthenticated($salesPointShortId);

            if ($salesPoint === null) {
                throw $exception;
            }

            return $salesPoint->setProducts(null);
        }
    }
}
