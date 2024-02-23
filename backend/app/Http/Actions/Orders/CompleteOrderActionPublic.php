<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Orders;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use TicketKitten\Exceptions\ResourceConflictException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CompleteOrderDTO;
use TicketKitten\Http\DataTransferObjects\CompleteOrderOrderDTO;
use TicketKitten\Http\Request\Order\CompleteOrderRequest;
use TicketKitten\Resources\Order\OrderResourcePublic;
use TicketKitten\Service\Handler\Order\CompleteOrderHandler;

class CompleteOrderActionPublic extends BaseAction
{
    public function __construct(private readonly CompleteOrderHandler $orderService)
    {
    }

    public function __invoke(CompleteOrderRequest $request, int $eventId, string $orderShortId): JsonResponse
    {
        try {
            $order = $this->orderService->handle($orderShortId, CompleteOrderDTO::fromArray([
                'order' => CompleteOrderOrderDTO::fromArray([
                    'first_name' => $request->validated('order.first_name'),
                    'last_name' => $request->validated('order.last_name'),
                    'email' => $request->validated('order.email'),
                    'address' => $request->validated('order.address'),
                    'questions' => $request->has('order.questions')
                        ? $request->input('order.questions')
                        : null,
                ]),
                'attendees' => $request->input('attendees'),
            ]));
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(OrderResourcePublic::class, $order);
    }
}
