<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Orders;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\CompleteOrderDTO;
use HiEvents\Http\DataTransferObjects\CompleteOrderOrderDTO;
use HiEvents\Http\Request\Order\CompleteOrderRequest;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Service\Handler\Order\CompleteOrderHandler;

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
