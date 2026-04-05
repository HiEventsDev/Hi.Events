<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Application\Handlers\Order\ApproveOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\ApproveOrderDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApproveOrderAction extends BaseAction
{
    public function __construct(
        private readonly ApproveOrderHandler $approveOrderHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $orderId): JsonResponse|Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $order = $this->approveOrderHandler->handle(new ApproveOrderDTO($eventId, $orderId));
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: OrderResource::class,
            data: $order,
        );
    }
}
