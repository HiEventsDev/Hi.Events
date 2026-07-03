<?php

namespace HiEvents\Http\Actions\Orders\Payment;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\RefundOrderRequest;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\RefundOrderHandler as RazorpayRefundOrderHandler;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler as StripeRefundOrderHandler;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Throwable;

class RefundOrderAction extends BaseAction
{
    public function __construct(
        private readonly StripeRefundOrderHandler   $stripeRefundOrderHandler,
        private readonly RazorpayRefundOrderHandler $razorpayRefundOrderHandler,
        private readonly OrderRepositoryInterface   $orderRepository
    ) {
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function __invoke(RefundOrderRequest $request, int $eventId, int $orderId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $order = $this->orderRepository->findById($orderId);
            
            $refundOrderDTO = RefundOrderDTO::fromArray(array_merge($request->validated(), [
                'event_id' => $eventId,
                'order_id' => $orderId,
            ]));

            if ($order->getPaymentProvider() === \HiEvents\DomainObjects\Enums\PaymentProviders::RAZORPAY->name) {
                $order = $this->razorpayRefundOrderHandler->handle($refundOrderDTO);
            } else {
                $order = $this->stripeRefundOrderHandler->handle($refundOrderDTO);
            }
        } catch (ApiErrorException|RefundNotPossibleException|\HiEvents\Exceptions\Razorpay\RazorpayClientConfigurationException $exception) {
            throw ValidationException::withMessages([
                'amount' => $exception instanceof ApiErrorException
                    ? 'Payment gateway error: ' . $exception->getMessage()
                    : $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(OrderResource::class, $order);
    }
}
