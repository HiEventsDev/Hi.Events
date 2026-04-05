<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Domain\Order\ProvisionalReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetProvisionalOrdersAction extends BaseAction
{
    public function __construct(
        private readonly ProvisionalReservationService $provisionalService,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $orders = $this->provisionalService->getProvisionalOrders($eventId);

        return $this->resourceResponse(
            resource: OrderResource::class,
            data: $orders,
        );
    }
}
