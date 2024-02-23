<?php

namespace TicketKitten\Http\Actions\Orders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\Status\OrderStatus;
use TicketKitten\Exceptions\ResourceConflictException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CancelOrderDTO;
use TicketKitten\Resources\Order\OrderResource;
use TicketKitten\Service\Handler\Order\CancelOrderHandler;

class CancelOrderAction extends BaseAction
{
    public function __construct(
        private readonly CancelOrderHandler $cancelOrderHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $orderId): JsonResponse|Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $order = $this->cancelOrderHandler->handle(new CancelOrderDTO($eventId, $orderId));
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), HttpResponse::HTTP_CONFLICT);
        }

        return $this->resourceResponse(OrderResource::class, $order->setStatus(OrderStatus::CANCELLED->name));
    }
}
