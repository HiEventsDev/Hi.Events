<?php

namespace HiEvents\Service\Handler\Order;

use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\DataTransferObjects\CancelOrderDTO;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Service\Common\Order\OrderCancelService;

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
