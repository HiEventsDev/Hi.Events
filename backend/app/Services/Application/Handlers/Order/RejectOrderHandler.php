<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\ApproveOrderDTO;
use Psr\Log\LoggerInterface;

class RejectOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface          $logger,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(ApproveOrderDTO $dto): OrderDomainObject
    {
        $this->logger->info(__('Rejecting order'), [
            'orderId' => $dto->orderId,
            'eventId' => $dto->eventId,
        ]);

        /** @var OrderDomainObject $order */
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findFirstWhere([
                OrderDomainObjectAbstract::ID => $dto->orderId,
                OrderDomainObjectAbstract::EVENT_ID => $dto->eventId,
            ]);

        if ($order->getStatus() !== OrderStatus::AWAITING_APPROVAL->name) {
            throw new ResourceConflictException(__('Order is not awaiting approval'));
        }

        $updatedOrder = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray($dto->orderId, [
                OrderDomainObjectAbstract::STATUS => OrderStatus::CANCELLED->name,
            ]);

        event(new OrderStatusChangedEvent(
            order: $updatedOrder,
            sendEmails: false,
        ));

        return $updatedOrder;
    }
}
