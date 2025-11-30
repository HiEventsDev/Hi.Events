<?php

namespace HiEvents\Http\Actions\TicketLookup;

use HiEvents\Exceptions\InvalidTicketLookupTokenException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Application\Handlers\TicketLookup\DTO\GetOrdersByLookupTokenDTO;
use HiEvents\Services\Application\Handlers\TicketLookup\GetOrdersByLookupTokenHandler;
use Illuminate\Http\JsonResponse;

class GetOrdersByLookupTokenAction extends BaseAction
{
    public function __construct(
        private readonly GetOrdersByLookupTokenHandler $getOrdersByLookupTokenHandler,
    ) {
    }

    public function __invoke(string $token): JsonResponse
    {
        try {
            $orders = $this->getOrdersByLookupTokenHandler->handle(
                new GetOrdersByLookupTokenDTO(
                    token: $token,
                )
            );

            return $this->resourceResponse(
                resource: OrderResourcePublic::class,
                data: $orders,
            );
        } catch (InvalidTicketLookupTokenException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
            );
        }
    }
}
