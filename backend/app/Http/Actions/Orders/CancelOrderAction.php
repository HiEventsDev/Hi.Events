<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Handlers\Order\CancelOrderHandler;
use HiEvents\Services\Handlers\Order\DTO\CancelOrderDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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
