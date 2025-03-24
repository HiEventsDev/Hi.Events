<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\CancelOrderDTO;
use HiEvents\Services\Domain\Order\OrderCancelService;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class CancelOrderHandler
{
    public function __construct(
        private readonly OrderCancelService       $orderCancelService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DatabaseManager          $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws ResourceConflictException
     */
    public function handle(CancelOrderDTO $cancelOrderDTO): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($cancelOrderDTO) {
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
        });
    }
}
