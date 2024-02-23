<?php

namespace TicketKitten\Http\Actions\Orders\Payment\Stripe;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use TicketKitten\Exceptions\Stripe\CreatePaymentIntentFailedException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Service\Handler\Order\Payment\Stripe\CreatePaymentIntentHandler;

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
