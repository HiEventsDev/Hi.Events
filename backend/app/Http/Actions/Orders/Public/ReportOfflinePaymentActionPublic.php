<?php

namespace HiEvents\Http\Actions\Orders\Public;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\ReportOfflinePaymentRequest;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Application\Handlers\Order\Public\ReportOfflinePaymentPublicHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class ReportOfflinePaymentActionPublic extends BaseAction
{
    public function __construct(
        private readonly ReportOfflinePaymentPublicHandler $handler,
    ) {}

    public function __invoke(ReportOfflinePaymentRequest $request, int $eventId, string $orderShortId): JsonResponse
    {
        try {
            $order = $this->handler->handle(
                eventId: $eventId,
                orderShortId: $orderShortId,
                note: $request->validated('note'),
            );
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
