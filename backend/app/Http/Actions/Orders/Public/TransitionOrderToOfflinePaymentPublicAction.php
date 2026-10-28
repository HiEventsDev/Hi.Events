<?php

namespace HiEvents\Http\Actions\Orders\Public;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Application\Handlers\Order\DTO\TransitionOrderToOfflinePaymentPublicDTO;
use HiEvents\Services\Application\Handlers\Order\TransitionOrderToOfflinePaymentHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TransitionOrderToOfflinePaymentPublicAction extends BaseAction
{
    public function __construct(
        private readonly TransitionOrderToOfflinePaymentHandler $initializeOrderOfflinePaymentPublicHandler,
    ) {}

    public function __invoke(Request $request, int $eventId, string $orderShortId): JsonResponse
    {
        try {
            $order = $this->initializeOrderOfflinePaymentPublicHandler->handle(
                TransitionOrderToOfflinePaymentPublicDTO::fromArray([
                    'orderShortId' => $orderShortId,
                ]),
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
