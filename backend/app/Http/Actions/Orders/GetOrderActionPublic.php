<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Handlers\Order\DTO\GetOrderPublicDTO;
use HiEvents\Services\Handlers\Order\GetOrderPublicHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrderActionPublic extends BaseAction
{
    public function __construct(
        private readonly GetOrderPublicHandler $getOrderPublicHandler
    )
    {
    }

    public function __invoke(int $eventId, string $orderShortId, Request $request): JsonResponse
    {
        $order = $this->getOrderPublicHandler->handle(new GetOrderPublicDTO(
            eventId: $eventId,
            orderShortId: $orderShortId,
            includeEventInResponse: $this->isIncludeRequested($request, 'event'),
        ));

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
