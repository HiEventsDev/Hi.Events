<?php

namespace HiEvents\Http\Actions\Common\Webhooks;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\DTO\StripeWebhookDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\IncomingWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class StripeIncomingWebhookAction extends BaseAction
{
    public function __invoke(Request $request): Response
    {
        try {
            $headerSignature = $request->server('HTTP_STRIPE_SIGNATURE');
            $payload = $request->getContent();

            dispatch(static function (IncomingWebhookHandler $handler) use ($headerSignature, $payload) {
                $handler->handle(new StripeWebhookDTO(
                    headerSignature: $headerSignature,
                    payload: $payload,
                ));
            })->catch(function (Throwable $exception) use ($payload) {
                logger()->error(__('Failed to handle incoming Stripe webhook'), [
                    'exception' => $exception,
                    'payload' => $payload,
                ]);
            });

        } catch (Throwable $exception) {
            logger()?->error($exception->getMessage(), $exception->getTrace());
            return $this->noContentResponse(ResponseCodes::HTTP_BAD_REQUEST);
        }

        return $this->noContentResponse();
    }
}
