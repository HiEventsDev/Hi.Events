<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Orders;

use Illuminate\Http\JsonResponse;
use Throwable;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CreateOrderPublicDTO;
use TicketKitten\Http\DataTransferObjects\TicketOrderDetailsDTO;
use TicketKitten\Http\Request\Order\CreateOrderRequest;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Resources\Order\OrderResourcePublic;
use TicketKitten\Service\Handler\Order\CreateOrderHandler;

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
