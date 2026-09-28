<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\SalesPoint;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Cashless\CashlessSalesPointResource;
use HiEvents\Services\Application\Handlers\Cashless\SalesPoint\GetCashlessSalesPointHandler;
use Illuminate\Http\JsonResponse;

class GetCashlessSalesPointAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessSalesPointHandler $getCashlessSalesPointHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $eventId, int $salesPointId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: CashlessSalesPointResource::class,
            data: $this->getCashlessSalesPointHandler->handle($eventId, $salesPointId),
        );
    }
}
