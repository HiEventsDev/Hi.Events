<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Cashless\SalesPoint;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Cashless\CashlessSalesPointResource;
use HiEvents\Services\Application\Handlers\Cashless\SalesPoint\GetCashlessSalesPointsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCashlessSalesPointsAction extends BaseAction
{
    public function __construct(
        private readonly GetCashlessSalesPointsHandler $getCashlessSalesPointsHandler,
    ) {}

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->filterableResourceResponse(
            resource: CashlessSalesPointResource::class,
            data: $this->getCashlessSalesPointsHandler->handle($eventId, $this->getPaginationQueryParams($request)),
            domainObject: CashlessSalesPointDomainObject::class,
        );
    }
}
