<?php

namespace HiEvents\Http\Actions\Orders\Payment\Stripe;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\Order\Payment\Stripe\GetPaymentIntentHandler;
use Illuminate\Http\JsonResponse;

class GetPaymentIntentActionPublic extends BaseAction
{
    public function __construct(
        private readonly GetPaymentIntentHandler $getPaymentIntentHandler,
    )
    {
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        $createIntent = $this->getPaymentIntentHandler->handle(
            eventId: $eventId,
            orderShortId: $orderShortId
        );

        return new JsonResponse($createIntent->toArray());
    }
}
