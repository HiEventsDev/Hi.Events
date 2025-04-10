<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\EditOrderRequest;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Application\Handlers\Order\DTO\EditOrderDTO;
use HiEvents\Services\Application\Handlers\Order\EditOrderHandler;
use Illuminate\Http\JsonResponse;

class EditOrderAction extends BaseAction
{
    public function __construct(
        private readonly EditOrderHandler $handler
    )
    {
    }

    public function __invoke(EditOrderRequest $request, int $eventId, int $orderId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $order = $this->handler->handle(new EditOrderDTO(
            id: $orderId,
            eventId: $eventId,
            firstName: $request->validated('first_name'),
            lastName: $request->validated('last_name'),
            email: $request->validated('email'),
            notes: $request->validated('notes'),
        ));

        return $this->resourceResponse(OrderResource::class, $order);
    }

}
