<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\PaymentVerificationFailedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\VerifyRazorpayPaymentRequest;
use HiEvents\Services\Application\Handlers\Order\DTO\VerifyRazorpayPaymentDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\VerifyRazorpayPaymentHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifyRazorpayPaymentActionPublic extends BaseAction
{
    public function __construct(
        private readonly VerifyRazorpayPaymentHandler $verifyRazorpayPaymentHandler,
    ) {
    }

    public function __invoke(int $eventId, string $orderShortId, VerifyRazorpayPaymentRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $verifyRazorpayPaymentDTO = new VerifyRazorpayPaymentDTO(
                razorpay_payment_id: $validated['razorpay_payment_id'],
                razorpay_order_id: $validated['razorpay_order_id'],
                razorpay_signature: $validated['razorpay_signature'],
            );

            $order = $this->verifyRazorpayPaymentHandler->handle(
                $orderShortId,
                $verifyRazorpayPaymentDTO
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