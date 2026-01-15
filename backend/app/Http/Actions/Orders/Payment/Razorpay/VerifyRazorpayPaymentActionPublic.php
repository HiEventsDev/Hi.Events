<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\PaymentVerificationFailedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\VerifyRazorpayPaymentHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifyRazorpayPaymentActionPublic extends BaseAction
{
    public function __construct(
        private readonly VerifyRazorpayPaymentHandler $verifyRazorpayPaymentHandler,
    )
    {
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        try {
            $order = $this->verifyRazorpayPaymentHandler->handle(
                $orderShortId,
                request()->all()
            );
        } catch (PaymentVerificationFailedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse([
            'message' => __('Payment verified successfully'),
            'order' => $order->toArray(),
        ]);
    }
}