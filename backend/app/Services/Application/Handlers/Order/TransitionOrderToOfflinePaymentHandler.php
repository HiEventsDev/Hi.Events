<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\TransitionOrderToOfflinePaymentPublicDTO;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use Illuminate\Database\DatabaseManager;

class TransitionOrderToOfflinePaymentHandler
{
    public function __construct(
        private readonly ProductQuantityUpdateService     $productQuantityUpdateService,
        private readonly OrderRepositoryInterface         $orderRepository,
        private readonly DatabaseManager                  $databaseManager,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,

    )
    {
    }

    public function handle(TransitionOrderToOfflinePaymentPublicDTO $dto): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            /** @var OrderDomainObjectAbstract $order */
            $order = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findByShortId($dto->orderShortId);

            $this->validateOfflinePaymentsAreEnabled($order);

            $this->updateOrderStatuses($order->getId());

            $this->productQuantityUpdateService->updateQuantitiesFromOrder($order);

            $order = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findById($order->getId());

            OrderStatusChangedEvent::dispatch($order);

            return $order;
        });
    }

    private function updateOrderStatuses(int $orderId): void
    {
        $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray($orderId, [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name,
                OrderDomainObjectAbstract::STATUS => OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            ]);
    }

    public function validateOfflinePaymentsAreEnabled(OrderDomainObjectAbstract $order): void
    {
        $eventSettings = $this->eventSettingsRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
        ]);

        if (collect($eventSettings->getPaymentProviders())->contains(PaymentProviders::OFFLINE->value) === false) {
            throw new UnauthorizedException('This event does not support offline payments');
        }
    }
}
