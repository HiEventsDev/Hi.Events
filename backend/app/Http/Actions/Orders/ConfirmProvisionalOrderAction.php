<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Domain\Order\ProvisionalReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfirmProvisionalOrderAction extends BaseAction
{
    public function __construct(
        private readonly ProvisionalReservationService $provisionalService,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $orderId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $order = $this->provisionalService->confirmProvisionalOrder($orderId);

        return $this->resourceResponse(
            resource: OrderResource::class,
            data: $order,
        );
    }
}
