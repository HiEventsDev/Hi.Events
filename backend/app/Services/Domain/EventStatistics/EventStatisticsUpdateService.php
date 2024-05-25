<?php

namespace HiEvents\Services\Domain\EventStatistics;

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
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

/**
 * @todo - Break this up into smaller services
 */
readonly class EventStatisticsUpdateService
{
    public function __construct(
        private PromoCodeRepositoryInterface           $promoCodeRepository,
        private TicketRepositoryInterface              $ticketRepository,
        private EventStatisticRepositoryInterface      $eventStatisticsRepository,
        private EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        private DatabaseManager                        $databaseManager,
        private OrderRepositoryInterface               $orderRepository,
        private LoggerInterface                        $logger,
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
        $this->updateAggregateStatsWithRefund($order, $amount);
        $this->updateEventDailyStatsWithRefund($order, $amount);
    }

    private function updateEventDailyStatsWithRefund(OrderDomainObject $order, MoneyValue $amount): void
    {
        $eventDailyStatistic = $this->eventDailyStatisticRepository->findFirstWhere(
            where: [
                'event_id' => $order->getEventId(),
                'date' => (new Carbon($order->getCreatedAt()))->format('Y-m-d'),
            ]
        );

        if ($eventDailyStatistic === null) {
            throw new ResourceNotFoundException("Event daily statistics not found.");
        }

        // Calculate the proportion of the refund to the total order amount
        $refundProportion = $amount->toFloat() / $order->getTotalGross();

        // Adjust the total_tax and total_fee based on the refund proportion
        $adjustedTotalTax = $eventDailyStatistic->getTotalTax() - ($order->getTotalTax() * $refundProportion);
        $adjustedTotalFee = $eventDailyStatistic->getTotalFee() - ($order->getTotalFee() * $refundProportion);

        // Update the event daily statistics with the new values
        $this->eventDailyStatisticRepository->updateWhere(
            attributes: [
                'sales_total_gross' => $eventDailyStatistic->getSalesTotalGross() - $amount->toFloat(),
                'total_refunded' => $eventDailyStatistic->getTotalRefunded() + $amount->toFloat(),
                'total_tax' => $adjustedTotalTax,
                'total_fee' => $adjustedTotalFee,
            ],
            where: [
                'event_id' => $order->getEventId(),
                'date' => (new Carbon($order->getCreatedAt()))->format('Y-m-d'),
            ]
        );

        $this->logger->info(
            message: __('Event daily statistics updated for event :event_id with total refunded amount of :amount', [
                'event_id' => $order->getEventId(),
                'amount' => $amount->toFloat(),
            ]),
            context: [
                'event_id' => $order->getEventId(),
                'amount' => $amount->toFloat(),
                'original_total_gross' => $eventDailyStatistic->getSalesTotalGross(),
                'original_total_refunded' => $eventDailyStatistic->getTotalRefunded(),
                'original_total_tax' => $eventDailyStatistic->getTotalTax(),
                'original_total_fee' => $eventDailyStatistic->getTotalFee(),
                'tax_refunded' => $adjustedTotalTax,
                'fee_refunded' => $adjustedTotalFee,
            ]);
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

    /**
     * @param OrderDomainObject $order
     * @param MoneyValue $amount
     * @return void
     */
    private function updateAggregateStatsWithRefund(OrderDomainObject $order, MoneyValue $amount): void
    {
        $eventStatistics = $this->eventStatisticsRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
        ]);

        if (!$eventStatistics) {
            throw new ResourceNotFoundException("Event statistics not found.");
        }

        // Calculate the proportion of the refund to the total order amount
        $refundProportion = $amount->toFloat() / $order->getTotalGross();

        // Adjust the total_tax and total_fee based on the refund proportion
        $adjustedTotalTax = $eventStatistics->getTotalTax() - ($order->getTotalTax() * $refundProportion);
        $adjustedTotalFee = $eventStatistics->getTotalFee() - ($order->getTotalFee() * $refundProportion);

        // Update the event statistics with the new values
        $this->eventStatisticsRepository->updateWhere(
            attributes: [
                'sales_total_gross' => $eventStatistics->getSalesTotalGross() - $amount->toFloat(),
                'total_refunded' => $eventStatistics->getTotalRefunded() + $amount->toFloat(),
                'total_tax' => $adjustedTotalTax,
                'total_fee' => $adjustedTotalFee,
            ],
            where: [
                'event_id' => $order->getEventId(),
            ]
        );

        $this->logger->info(
            message: __('Event statistics updated for event :event_id with total refunded amount of :amount', [
                'event_id' => $order->getEventId(),
                'amount' => $amount->toFloat(),
            ]),
            context: [
                'event_id' => $order->getEventId(),
                'amount' => $amount->toFloat(),
                'original_total_gross' => $eventStatistics->getSalesTotalGross(),
                'original_total_refunded' => $eventStatistics->getTotalRefunded(),
                'original_total_tax' => $eventStatistics->getTotalTax(),
                'original_total_fee' => $eventStatistics->getTotalFee(),
                'tax_refunded' => $adjustedTotalTax,
                'fee_refunded' => $adjustedTotalFee,
            ]);
    }
}
