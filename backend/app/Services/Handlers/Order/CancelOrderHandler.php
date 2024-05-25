<?php

namespace HiEvents\Services\Handlers\Order;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Handlers\Order\DTO\CancelOrderDTO;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

readonly class CancelOrderHandler
{
    public function __construct(
        private OrderCancelService       $orderCancelService,
        private OrderRepositoryInterface $orderRepository
    )
    {
    }

    /**
     * @throws Throwable
     * @throws ResourceConflictException
     */
    public function handle(CancelOrderDTO $cancelOrderDTO): OrderDomainObject
    {
        $order = $this->orderRepository
            ->findFirstWhere([
                OrderDomainObjectAbstract::EVENT_ID => $cancelOrderDTO->eventId,
                OrderDomainObjectAbstract::ID => $cancelOrderDTO->orderId,
            ]);

        if (!$order) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        if ($order->isOrderCancelled()) {
            throw new ResourceConflictException(__('Order already cancelled'));
        }

        $this->orderCancelService->cancelOrder($order);

        return $this->orderRepository->findById($order->getId());
    }
}
