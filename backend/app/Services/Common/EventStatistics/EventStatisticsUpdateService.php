<?php

namespace HiEvents\Services\Common\EventStatistics;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Throwable;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Exceptions\EventStatisticsVersionMismatchException;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Values\MoneyValue;

readonly class EventStatisticsUpdateService
{
    public function __construct(
        private PromoCodeRepositoryInterface           $promoCodeRepository,
        private TicketRepositoryInterface              $ticketRepository,
        private EventStatisticRepositoryInterface      $eventStatisticsRepository,
        private EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        private DatabaseManager                        $databaseManager,
        private OrderRepositoryInterface               $orderRepository,
    )
    {
    }

    /**
     * @throws EventStatisticsVersionMismatchException
     * @throws Throwable
     */
    public function updateStatistics(OrderDomainObject $order): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());

        $this->databaseManager->transaction(function () use ($order) {
            $this->updateEventStats($order);
            $this->updateEventDailyStats($order);
            $this->updatePromoCodeCounts($order);
            $this->updateTicketStatistics($order);
        });
    }

    public function updateEventStatsTotalRefunded(OrderDomainObject $order, MoneyValue $amount): void
    {
        $this->eventStatisticsRepository->updateWhere(
            attributes: [
                'total_refunded' => $amount->toFloat(),
            ],
            where: [
                'event_id' => $order->getEventId(),
            ]
        );
    }

    private function updatePromoCodeCounts(OrderDomainObject $order): void
    {
        if ($order->getPromoCodeId() !== null) {
            $this->promoCodeRepository->increment(
                id: $order->getPromoCodeId(),
                column: PromoCodeDomainObjectAbstract::ORDER_USAGE_COUNT,
            );
            $this->promoCodeRepository->increment(
                id: $order->getPromoCodeId(),
                column: PromoCodeDomainObjectAbstract::ATTENDEE_USAGE_COUNT,
                amount: $order->getOrderItems()?->sum('quantity'),
            );
        }
    }

    private function updateTicketStatistics(OrderDomainObject $order): void
    {
        foreach ($order->getOrderItems() as $orderItem) {
            $this->ticketRepository->increment(
                $orderItem->getTicketId(),
                TicketDomainObjectAbstract::SALES_VOLUME,
                $orderItem->getTotalBeforeAdditions(),
            );
        }
    }

    /**
     * @param OrderDomainObject $order
     * @return void
     * @throws EventStatisticsVersionMismatchException
     */
    private function updateEventStats(OrderDomainObject $order): void
    {
        $eventStatistics = $this->eventStatisticsRepository->findFirstWhere(
            where: [
                'event_id' => $order->getEventId(),
            ]
        );

        if ($eventStatistics === null) {
            $this->eventStatisticsRepository->create([
                'event_id' => $order->getEventId(),
                'tickets_sold' => $order->getOrderItems()
                    ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()),
                'sales_total_gross' => $order->getTotalGross(),
                'sales_total_before_additions' => $order->getTotalBeforeAdditions(),
                'total_tax' => $order->getTotalTax(),
                'total_fee' => $order->getTotalFee(),
                'orders_created' => 1,
            ]);

            return;
        }

        $update = $this->eventStatisticsRepository->updateWhere(
            attributes: [
                'tickets_sold' => $eventStatistics->getTicketsSold() + $order->getOrderItems()
                        ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()),
                'sales_total_gross' => $eventStatistics->getSalesTotalGross() + $order->getTotalGross(),
                'sales_total_before_additions' => $eventStatistics->getSalesTotalBeforeAdditions() + $order->getTotalBeforeAdditions(),
                'total_tax' => $eventStatistics->getTotalTax() + $order->getTotalTax(),
                'total_fee' => $eventStatistics->getTotalFee() + $order->getTotalFee(),
                'version' => $eventStatistics->getVersion() + 1,
                'orders_created' => $eventStatistics->getOrdersCreated() + 1,

            ],
            where: [
                'event_id' => $order->getEventId(),
                'version' => $eventStatistics->getVersion(),
            ]
        );

        if ($update === 0) {
            throw new EventStatisticsVersionMismatchException(
                'Event statistics version mismatch. Expected version '
                . $eventStatistics->getVersion() . ' but got ' . $eventStatistics->getVersion() + 1
                . ' for event ' . $order->getEventId(),
            );
        }
    }

    /**
     * @throws EventStatisticsVersionMismatchException
     */
    private function updateEventDailyStats(OrderDomainObject $order): void
    {
        $eventDailyStatistic = $this->eventDailyStatisticRepository->findFirstWhere(
            where: [
                'event_id' => $order->getEventId(),
                'date' => (new Carbon($order->getCreatedAt()))->format('Y-m-d'),
            ]
        );

        if ($eventDailyStatistic === null) {
            $this->eventDailyStatisticRepository->create([
                'event_id' => $order->getEventId(),
                'date' => (new Carbon($order->getCreatedAt()))->format('Y-m-d'),
                'tickets_sold' => $order->getOrderItems()?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()),
                'sales_total_gross' => $order->getTotalGross(),
                'sales_total_before_additions' => $order->getTotalBeforeAdditions(),
                'total_tax' => $order->getTotalTax(),
                'total_fee' => $order->getTotalFee(),
                'orders_created' => 1,
            ]);
            return;
        }

        $update = $this->eventDailyStatisticRepository->updateWhere(
            attributes: [
                'tickets_sold' => $eventDailyStatistic->getTicketsSold() + $order->getOrderItems()->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()),
                'sales_total_gross' => $eventDailyStatistic->getSalesTotalGross() + $order->getTotalGross(),
                'sales_total_before_additions' => $eventDailyStatistic->getSalesTotalBeforeAdditions() + $order->getTotalBeforeAdditions(),
                'total_tax' => $eventDailyStatistic->getTotalTax() + $order->getTotalTax(),
                'total_fee' => $eventDailyStatistic->getTotalFee() + $order->getTotalFee(),
                'version' => $eventDailyStatistic->getVersion() + 1,
                'orders_created' => $eventDailyStatistic->getOrdersCreated() + 1,
            ],
            where: [
                'event_id' => $order->getEventId(),
                'date' => (new Carbon($order->getCreatedAt()))->format('Y-m-d'),
                'version' => $eventDailyStatistic->getVersion(),
            ],
        );

        if ($update === 0) {
            throw new EventStatisticsVersionMismatchException(
                'Event daily statistics version mismatch. Expected version '
                . $eventDailyStatistic->getVersion() . ' but got ' . $eventDailyStatistic->getVersion() + 1
                . ' for event ' . $order->getEventId(),
            );
        }
    }
}
