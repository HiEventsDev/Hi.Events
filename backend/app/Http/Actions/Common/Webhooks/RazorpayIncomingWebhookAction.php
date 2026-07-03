<?php

namespace HiEvents\Http\Actions\Common\Webhooks;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\IncomingWebhookHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RazorpayIncomingWebhookAction extends BaseAction
{
    public function __construct(
        private readonly IncomingWebhookHandler $incomingWebhookHandler,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $signature = $request->header('x-razorpay-signature');

        if (!$signature) {
            return response()->noContent(Response::HTTP_BAD_REQUEST);
        }

        // We dispatch the handler asynchronously to ensure the webhook returns a 200/204
        // to Razorpay immediately, avoiding timeout retries.
        dispatch(function () use ($request, $signature) {
            $this->incomingWebhookHandler->handle(
                rawBody:   $request->getContent(),
                signature: $signature,
                payload:   $request->all(),
            );
        });

        return response()->noContent();
    }
}
