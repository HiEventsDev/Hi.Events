<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Exceptions\Razorpay\CreateOrderFailedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\CreateRazorpayOrderHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreateRazorpayOrderActionPublic extends BaseAction
{
    public function __construct(
        private readonly CreateRazorpayOrderHandler $createRazorpayOrderHandler,
    ) {
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        try {
            $razorpayOrder = $this->createRazorpayOrderHandler->handle($orderShortId);
        } catch (CreateOrderFailedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse([
            'razorpay_order_id' => $razorpayOrder->id,
            'key_id' => $razorpayOrder->keyId,
            'amount' => $razorpayOrder->amount,
            'currency' => $razorpayOrder->currency,
        ]);
    }
}