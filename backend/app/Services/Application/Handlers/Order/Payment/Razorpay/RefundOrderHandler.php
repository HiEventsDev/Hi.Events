<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\CancelOrderService;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Traits\RefundOrderTrait;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayRefundService;
use HiEvents\Values\MoneyValue;
use Throwable;

class RefundOrderHandler
{
    use RefundOrderTrait;

    public function __construct(
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly RazorpayRefundService             $razorpayRefundService,
        private readonly CancelOrderService                $cancelOrderService,
    ) {
    }

    /**
     * @throws RefundNotPossibleException|Throwable
     */
    public function handle(RefundOrderDTO $refundOrderDTO): OrderDomainObject
    {
        $order = $this->orderRepository->findById($refundOrderDTO->order_id);

        $this->validateRefund($order, $refundOrderDTO);

        $razorpayOrder = $this->razorpayOrdersRepository->findFirstWhere([
            RazorpayOrderDomainObjectAbstract::ORDER_ID => $order->getId(),
        ]);

        if (!$razorpayOrder) {
            throw new RefundNotPossibleException(__('Refund not possible: no Razorpay order found.'));
        }

        $this->razorpayRefundService->refundPayment(
            amount: new MoneyValue($refundOrderDTO->amount, $order->getCurrency()),
            razorpayOrder: $razorpayOrder,
        );

        if ($refundOrderDTO->cancel_order) {
            $this->cancelOrderService->cancelOrder($order->getId(), false, $refundOrderDTO->notify_buyer);
        }

        return $order;
    }
}
