<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\CreateOrderRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Handlers\Order\DTO\TicketOrderDetailsDTO;
use Illuminate\Http\JsonResponse;
use Throwable;

class CreateOrderActionPublic extends BaseAction
{
    private CreateOrderHandler $orderService;

    public function __construct(CreateOrderHandler $orderHandler)
    {
        $this->orderService = $orderHandler;
    }

    /**
     * @throws Throwable
     */
    public function __invoke(CreateOrderRequest $request, int $eventId): JsonResponse
    {
        $order = $this->orderService->handle(
            $eventId,
            CreateOrderPublicDTO::fromArray([
                'is_user_authenticated' => $this->isUserAuthenticated(),
                'promo_code' => $request->input('promo_code'),
                'tickets' => TicketOrderDetailsDTO::collectionFromArray($request->input('tickets')),
            ])
        );

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
            statusCode: ResponseCodes::HTTP_CREATED
        );
    }
}
