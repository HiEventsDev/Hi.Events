<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\HandlePaymentCallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayPaymentCallbackActionPublic extends BaseAction
{
    public function __construct(
        private readonly HandlePaymentCallbackHandler $handlePaymentCallbackHandler,
    ) {
    }

    public function __invoke(Request $request, int $eventId, string $orderShortId): JsonResponse
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        $this->handlePaymentCallbackHandler->handle(
            razorpayOrderId:   $request->input('razorpay_order_id'),
            razorpayPaymentId: $request->input('razorpay_payment_id'),
            razorpaySignature: $request->input('razorpay_signature'),
        );

        return $this->jsonResponse(['status' => 'success']);
    }
}
