<?php

namespace HiEvents\Http\Actions\Orders\Payment\Stripe;

use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\CreatePaymentIntentHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreatePaymentIntentActionPublic extends BaseAction
{
    private CreatePaymentIntentHandler $createPaymentIntentHandler;

    public function __construct(CreatePaymentIntentHandler $createPaymentIntentHandler)
    {
        $this->createPaymentIntentHandler = $createPaymentIntentHandler;
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        try {
            $createIntent = $this->createPaymentIntentHandler->handle($orderShortId);
        } catch (CreatePaymentIntentFailedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse([
            'client_secret' => $createIntent->clientSecret,
            'account_id' => $createIntent->accountId,
        ]);
    }
}
