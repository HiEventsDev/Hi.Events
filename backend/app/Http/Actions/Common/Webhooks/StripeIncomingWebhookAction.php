<?php

namespace TicketKitten\Http\Actions\Common\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\StripeWebhookDTO;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Service\Handler\Order\Payment\Stripe\IncomingWebhookHandler;

class StripeIncomingWebhookAction extends BaseAction
{
    private IncomingWebhookHandler $webhookHandler;

    public function __construct(IncomingWebhookHandler $webhookHandler)
    {
        $this->webhookHandler = $webhookHandler;
    }

    public function __invoke(Request $request): Response
    {
        try {
            $this->webhookHandler->handle(new StripeWebhookDTO(
                headerSignature: $request->server('HTTP_STRIPE_SIGNATURE'),
                payload: $request->getContent(),
            ));
        } catch (Throwable $exception) {
            logger()?->error($exception->getMessage(), $exception->getTrace());
            return $this->noContentResponse(ResponseCodes::HTTP_BAD_REQUEST);
        }

        return $this->noContentResponse();
    }
}
