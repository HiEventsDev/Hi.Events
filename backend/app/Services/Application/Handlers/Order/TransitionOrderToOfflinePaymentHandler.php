<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\TransitionOrderToOfflinePaymentPublicDTO;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Database\DatabaseManager;

class TransitionOrderToOfflinePaymentHandler
{
    public function __construct(
        private readonly ProductQuantityUpdateService     $productQuantityUpdateService,
        private readonly OrderRepositoryInterface         $orderRepository,
        private readonly OrderItemRepositoryInterface     $orderItemRepository,
        private readonly DatabaseManager                  $databaseManager,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly DomainEventDispatcherService     $domainEventDispatcherService,
        private readonly CheckoutSessionManagementService $sessionManagementService,
        private readonly OrderManagementService           $orderManagementService,
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

            if ($order === null) {
                throw new ResourceConflictException(__('Order not found'));
            }

            if ($order->getSessionId() === null
                || !$this->sessionManagementService->verifySession($order->getSessionId())) {
                throw new UnauthorizedException(
                    __('Sorry, we could not verify your session. Please restart your order.')
                );
            }

            /** @var EventSettingDomainObject $eventSettings */
            $eventSettings = $this->eventSettingsRepository->findFirstWhere([
                'event_id' => $order->getEventId(),
            ]);

            $this->validateOfflinePayment($order, $eventSettings);

            $this->stripOnlineOnlyFees($order);

            $this->updateOrderStatuses($order->getId());

            $this->productQuantityUpdateService->updateQuantitiesFromOrder($order);

            $order = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findById($order->getId());

            event(new OrderStatusChangedEvent(
                order: $order,
                sendEmails: true,
                createInvoice: $eventSettings->getEnableInvoicing(),
            ));

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_CREATED,
                    orderId: $order->getId(),
                ),
            );

            return $order;
        });
    }

    private function updateOrderStatuses(int $orderId): void
    {
        $this->orderRepository
            ->updateFromArray($orderId, [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name,
                OrderDomainObjectAbstract::STATUS => OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
                OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::OFFLINE->value,
            ]);
    }

    /**
     * @throws ResourceConflictException
     */
    public function validateOfflinePayment(
        OrderDomainObject        $order,
        EventSettingDomainObject $settings,
    ): void
    {
        if (!$order->isOrderReserved()) {
            throw new ResourceConflictException(__('Order is not in the correct status to transition to offline payment'));
        }

        if ($order->isReservedOrderExpired()) {
            throw new ResourceConflictException(__('Order reservation has expired'));
        }

        if (collect($settings->getPaymentProviders())->contains(PaymentProviders::OFFLINE->value) === false) {
            throw new UnauthorizedException(__('Offline payments are not enabled for this event'));
        }
    }

    private function stripOnlineOnlyFees(OrderDomainObject $order): void
    {
        $orderItems = $order->getOrderItems();

        if ($orderItems === null || $orderItems->isEmpty()) {
            return;
        }

        $hasOnlineOnlyFees = false;

        foreach ($orderItems as $item) {
            $rollup = $item->getTaxesAndFeesRollup();

            if (is_string($rollup)) {
                $rollup = json_decode($rollup, true);
            }

            if (!is_array($rollup)) {
                continue;
            }

            $feeReduction = 0.0;
            $taxReduction = 0.0;

            foreach (['fees', 'taxes'] as $category) {
                if (!isset($rollup[$category])) {
                    continue;
                }

                $filtered = [];
                foreach ($rollup[$category] as $entry) {
                    if (!empty($entry['is_online_only'])) {
                        $hasOnlineOnlyFees = true;
                        if ($category === 'fees') {
                            $feeReduction += $entry['value'];
                        } else {
                            $taxReduction += $entry['value'];
                        }
                    } else {
                        $filtered[] = $entry;
                    }
                }
                $rollup[$category] = $filtered;
            }

            if ($feeReduction > 0 || $taxReduction > 0) {
                $newTotalFee = Currency::round($item->getTotalServiceFee() - $feeReduction);
                $newTotalTax = Currency::round($item->getTotalTax() - $taxReduction);
                $newTotalGross = Currency::round($item->getTotalBeforeAdditions() + $newTotalTax + $newTotalFee);

                $this->orderItemRepository->updateFromArray($item->getId(), [
                    'total_service_fee' => $newTotalFee,
                    'total_tax' => $newTotalTax,
                    'total_gross' => $newTotalGross,
                    'taxes_and_fees_rollup' => json_encode($rollup),
                ]);
            }
        }

        if ($hasOnlineOnlyFees) {
            $order = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findById($order->getId());

            $this->orderManagementService->updateOrderTotals($order, $order->getOrderItems());
        }
    }
}
