<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\MarkOrderAsPaidDTO;

class MarkOrderAsPaidHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(MarkOrderAsPaidDTO $dto): OrderDomainObject
    {
        /** @var OrderDomainObject $order */
        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::ID => $dto->orderId,
            OrderDomainObjectAbstract::EVENT_ID => $dto->eventId,
        ]);

        if ($order->getStatus() !== OrderStatus::AWAITING_OFFLINE_PAYMENT->name) {
            throw new ResourceConflictException(__('Order is not awaiting offline payment'));
        }

        $this->orderRepository->updateFromArray($dto->orderId, [
            OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
            OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
        ]);

        OrderStatusChangedEvent::dispatch($order);

        return $this->orderRepository->findById($dto->orderId);
    }
}
