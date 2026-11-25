<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\OrderAuditLog\TicketHistoryEntryResource;
use HiEvents\Services\Application\Handlers\OrderAuditLog\DTO\GetTicketHistoryDTO;
use HiEvents\Services\Application\Handlers\OrderAuditLog\GetTicketHistoryHandler;
use Illuminate\Http\JsonResponse;

class GetOrderHistoryAction extends BaseAction
{
    public function __construct(
        private readonly GetTicketHistoryHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $eventId, int $orderId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: TicketHistoryEntryResource::class,
            data: $this->handler->handle(new GetTicketHistoryDTO(event_id: $eventId, order_id: $orderId)),
        );
    }
}
