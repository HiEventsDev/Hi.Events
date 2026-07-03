<?php

namespace HiEvents\Http\Actions\Orders\Payment\Razorpay;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\CreateRazorpayOrderHandler;
use Illuminate\Http\JsonResponse;

class CreateRazorpayOrderActionPublic extends BaseAction
{
    public function __construct(
        private readonly CreateRazorpayOrderHandler $createRazorpayOrderHandler,
    ) {
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        $response = $this->createRazorpayOrderHandler->handle($orderShortId);

        return $this->jsonResponse($response->toArray());
    }
}
