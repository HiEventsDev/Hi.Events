<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Offline\RefundOfflineOrderHandler;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler as RefundStripeOrderHandler;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class RefundOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RefundStripeOrderHandler $refundStripeOrderHandler,
        private readonly RefundOfflineOrderHandler $refundOfflineOrderHandler,
    ) {}

    /**
     * @throws RefundNotPossibleException
     * @throws Throwable
     */
    public function handle(RefundOrderDTO $refundOrderDTO): OrderDomainObject
    {
        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::EVENT_ID => $refundOrderDTO->event_id,
            OrderDomainObjectAbstract::ID => $refundOrderDTO->order_id,
        ]);

        if (! $order) {
            throw new ResourceNotFoundException(__('Order :id not found for event :eventId', [
                'id' => $refundOrderDTO->order_id,
                'eventId' => $refundOrderDTO->event_id,
            ]));
        }

        if ($order->getPaymentProvider() === PaymentProviders::OFFLINE->name) {
            return $this->refundOfflineOrderHandler->handle($refundOrderDTO);
        }

        return $this->refundStripeOrderHandler->handle($refundOrderDTO);
    }
}
