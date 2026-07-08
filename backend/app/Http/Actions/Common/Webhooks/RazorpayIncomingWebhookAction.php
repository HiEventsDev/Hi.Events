<?php

namespace HiEvents\Http\Actions\Common\Webhooks;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\RazorpayWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RazorpayIncomingWebhookAction extends BaseAction
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        
        dispatch(static function (RazorpayWebhookHandler $handler) use ($payload, $signature) {
            $handler->handle($payload, $signature);
        })->catch(function (\Throwable $exception) use ($payload) {
            logger()->error(__('Failed to handle incoming Razorpay webhook'), [
                'exception' => $exception,
                'payload' => $payload,
            ]);
        });

        return $this->noContentResponse();
    }
}