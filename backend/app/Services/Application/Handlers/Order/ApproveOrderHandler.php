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
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Psr\Log\LoggerInterface;

class ApproveOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface      $orderRepository,
        private readonly ProductQuantityUpdateService   $productQuantityUpdateService,
        private readonly DomainEventDispatcherService   $domainEventDispatcherService,
        private readonly LoggerInterface                $logger,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(ApproveOrderDTO $dto): OrderDomainObject
    {
        $this->logger->info(__('Approving order'), [
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
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
            ]);

        $this->productQuantityUpdateService->updateQuantitiesFromOrder($updatedOrder);

        event(new OrderStatusChangedEvent(
            order: $updatedOrder,
            sendEmails: true,
        ));

        $this->domainEventDispatcherService->dispatch(
            new OrderEvent(
                type: DomainEventType::ORDER_CREATED,
                orderId: $updatedOrder->getId(),
            )
        );

        return $updatedOrder;
    }
}
