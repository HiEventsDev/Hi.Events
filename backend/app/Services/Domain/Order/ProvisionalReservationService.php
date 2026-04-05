<?php

namespace HiEvents\Services\Domain\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Support\Collection;

class ProvisionalReservationService
{
    public function __construct(
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly EventSettingsRepositoryInterface   $eventSettingsRepository,
    )
    {
    }

    public function isProvisionalBookingEnabled(int $eventId): bool
    {
        $settings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        return $settings?->getProvisionalBookingEnabled() ?? false;
    }

    public function getProvisionalBookingThreshold(int $eventId): ?float
    {
        $settings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        return $settings?->getProvisionalBookingThreshold();
    }

    public function shouldCreateProvisionalOrder(int $eventId, float $orderTotal): bool
    {
        if (!$this->isProvisionalBookingEnabled($eventId)) {
            return false;
        }

        $threshold = $this->getProvisionalBookingThreshold($eventId);

        return $threshold !== null && $orderTotal >= $threshold;
    }

    /**
     * Mark an order as provisional with a deadline for confirmation.
     */
    public function markAsProvisional(int $orderId, int $eventId): OrderDomainObject
    {
        $settings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        $deadlineHours = $settings?->getProvisionalBookingDeadline() ?? 48;

        return $this->orderRepository->updateFromArray($orderId, [
            OrderDomainObjectAbstract::STATUS => OrderStatus::PROVISIONAL->name,
            OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::AWAITING_PAYMENT->name,
            OrderDomainObjectAbstract::RESERVED_UNTIL => Carbon::now()->addHours($deadlineHours)->toDateTimeString(),
        ]);
    }

    /**
     * Get all provisional orders for an event.
     */
    public function getProvisionalOrders(int $eventId): Collection
    {
        return $this->orderRepository->findWhere([
            [OrderDomainObjectAbstract::EVENT_ID, '=', $eventId],
            [OrderDomainObjectAbstract::STATUS, '=', OrderStatus::PROVISIONAL->name],
        ]);
    }

    /**
     * Confirm a provisional order — sets it to RESERVED awaiting payment.
     */
    public function confirmProvisionalOrder(int $orderId): OrderDomainObject
    {
        return $this->orderRepository->updateFromArray($orderId, [
            OrderDomainObjectAbstract::STATUS => OrderStatus::RESERVED->name,
            OrderDomainObjectAbstract::RESERVED_UNTIL => Carbon::now()->addHours(2)->toDateTimeString(),
        ]);
    }

    /**
     * Cancel expired provisional orders.
     */
    public function cancelExpiredProvisionalOrders(int $eventId): int
    {
        return $this->orderRepository->updateWhere(
            attributes: [
                OrderDomainObjectAbstract::STATUS => OrderStatus::CANCELLED->name,
            ],
            where: [
                [OrderDomainObjectAbstract::EVENT_ID, '=', $eventId],
                [OrderDomainObjectAbstract::STATUS, '=', OrderStatus::PROVISIONAL->name],
                [OrderDomainObjectAbstract::RESERVED_UNTIL, '<', Carbon::now()->toDateTimeString()],
            ],
        );
    }
}
