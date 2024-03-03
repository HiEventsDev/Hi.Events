<?php

namespace HiEvents\Http\Actions\Common\Webhooks;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Handlers\Order\Payment\Stripe\DTO\StripeWebhookDTO;
use HiEvents\Services\Handlers\Order\Payment\Stripe\IncomingWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

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
