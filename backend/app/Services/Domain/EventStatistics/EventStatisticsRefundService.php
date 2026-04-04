<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Values\MoneyValue;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EventStatisticsRefundService
{
    public function __construct(
        private readonly EventStatisticRepositoryInterface            $eventStatisticsRepository,
        private readonly EventDailyStatisticRepositoryInterface       $eventDailyStatisticRepository,
        private readonly EventOccurrenceStatisticRepositoryInterface      $eventOccurrenceStatisticRepository,
        private readonly EventOccurrenceDailyStatisticRepositoryInterface $eventOccurrenceDailyStatisticRepository,
        private readonly OrderRepositoryInterface                         $orderRepository,
        private readonly LoggerInterface                              $logger,
    )
    {
    }

    /**
     * Update statistics when an order is refunded
     */
    public function updateForRefund(OrderDomainObject $order, MoneyValue $refundAmount): void
    {
        $this->updateAggregateStatisticsForRefund($order, $refundAmount);
        $this->updateDailyStatisticsForRefund($order, $refundAmount);
        $this->updateOccurrenceStatisticsForRefund($order, $refundAmount);
        $this->updateOccurrenceDailyStatisticsForRefund($order, $refundAmount);
    }

    /**
     * Update aggregate statistics for a refund
     */
    private function updateAggregateStatisticsForRefund(OrderDomainObject $order, MoneyValue $refundAmount): void
    {
        $eventStatistics = $this->eventStatisticsRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
        ]);

        if (!$eventStatistics) {
            throw new ResourceNotFoundException("Event statistics not found for event {$order->getEventId()}");
        }

        // Calculate the proportion of the refund to the total order amount
        $refundProportion = $refundAmount->toFloat() / $order->getTotalGross();

        // Adjust the total_tax and total_fee based on the refund proportion
        $adjustedTotalTax = $eventStatistics->getTotalTax() - ($order->getTotalTax() * $refundProportion);
        $adjustedTotalFee = $eventStatistics->getTotalFee() - ($order->getTotalFee() * $refundProportion);

        $updates = [
            'sales_total_gross' => $eventStatistics->getSalesTotalGross() - $refundAmount->toFloat(),
            'total_refunded' => $eventStatistics->getTotalRefunded() + $refundAmount->toFloat(),
            'total_tax' => max(0, $adjustedTotalTax),
            'total_fee' => max(0, $adjustedTotalFee),
        ];

        $this->eventStatisticsRepository->updateWhere(
            attributes: $updates,
            where: [
                'event_id' => $order->getEventId(),
            ]
        );

        $this->logger->info(
            'Event aggregate statistics updated for refund',
            [
                'event_id' => $order->getEventId(),
                'order_id' => $order->getId(),
                'refund_amount' => $refundAmount->toFloat(),
                'refund_proportion' => $refundProportion,
                'original_total_gross' => $eventStatistics->getSalesTotalGross(),
                'original_total_refunded' => $eventStatistics->getTotalRefunded(),
                'tax_adjustment' => $order->getTotalTax() * $refundProportion,
                'fee_adjustment' => $order->getTotalFee() * $refundProportion,
            ]
        );
    }

    /**
     * Update daily statistics for a refund
     */
    private function updateDailyStatisticsForRefund(OrderDomainObject $order, MoneyValue $refundAmount): void
    {
        $orderDate = (new Carbon($order->getCreatedAt()))->format('Y-m-d');

        $eventDailyStatistic = $this->eventDailyStatisticRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
            'date' => $orderDate,
        ]);

        if ($eventDailyStatistic === null) {
            $this->logger->warning(
                'Event daily statistics not found for refund',
                [
                    'event_id' => $order->getEventId(),
                    'date' => $orderDate,
                    'order_id' => $order->getId(),
                ]
            );
            return;
        }

        // Calculate the proportion of the refund to the total order amount
        $refundProportion = $refundAmount->toFloat() / $order->getTotalGross();

        // Adjust the total_tax and total_fee based on the refund proportion
        $adjustedTotalTax = $eventDailyStatistic->getTotalTax() - ($order->getTotalTax() * $refundProportion);
        $adjustedTotalFee = $eventDailyStatistic->getTotalFee() - ($order->getTotalFee() * $refundProportion);

        $updates = [
            'sales_total_gross' => $eventDailyStatistic->getSalesTotalGross() - $refundAmount->toFloat(),
            'total_refunded' => $eventDailyStatistic->getTotalRefunded() + $refundAmount->toFloat(),
            'total_tax' => max(0, $adjustedTotalTax),
            'total_fee' => max(0, $adjustedTotalFee),
        ];

        $this->eventDailyStatisticRepository->updateWhere(
            attributes: $updates,
            where: [
                'event_id' => $order->getEventId(),
                'date' => $orderDate,
            ]
        );

        $this->logger->info(
            'Event daily statistics updated for refund',
            [
                'event_id' => $order->getEventId(),
                'order_id' => $order->getId(),
                'date' => $orderDate,
                'refund_amount' => $refundAmount->toFloat(),
                'refund_proportion' => $refundProportion,
                'original_total_gross' => $eventDailyStatistic->getSalesTotalGross(),
                'original_total_refunded' => $eventDailyStatistic->getTotalRefunded(),
                'tax_adjustment' => $order->getTotalTax() * $refundProportion,
                'fee_adjustment' => $order->getTotalFee() * $refundProportion,
            ]
        );
    }

    private function updateOccurrenceStatisticsForRefund(OrderDomainObject $order, MoneyValue $refundAmount): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());

        if ($order->getTotalGross() <= 0) {
            return;
        }

        $refundProportion = $refundAmount->toFloat() / $order->getTotalGross();
        $itemsByOccurrence = $this->groupItemsByOccurrence($order);

        foreach ($itemsByOccurrence as $occurrenceId => $items) {
            $existing = $this->eventOccurrenceStatisticRepository->findFirstWhere([
                'event_occurrence_id' => $occurrenceId,
            ]);

            if (!$existing) {
                continue;
            }

            $occurrenceGross = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalGross() ?? 0, $items));
            $occurrenceTax = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalTax() ?? 0, $items));
            $occurrenceFee = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalServiceFee() ?? 0, $items));
            $occurrenceRefundAmount = $occurrenceGross * $refundProportion;

            $this->eventOccurrenceStatisticRepository->updateWhere(
                attributes: [
                    'sales_total_gross' => max(0, $existing->getSalesTotalGross() - $occurrenceRefundAmount),
                    'total_refunded' => $existing->getTotalRefunded() + $occurrenceRefundAmount,
                    'total_tax' => max(0, $existing->getTotalTax() - ($occurrenceTax * $refundProportion)),
                    'total_fee' => max(0, $existing->getTotalFee() - ($occurrenceFee * $refundProportion)),
                ],
                where: [
                    'event_occurrence_id' => $occurrenceId,
                ]
            );
        }
    }

    private function updateOccurrenceDailyStatisticsForRefund(OrderDomainObject $order, MoneyValue $refundAmount): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());

        if ($order->getTotalGross() <= 0) {
            return;
        }

        $orderDate = (new Carbon($order->getCreatedAt()))->format('Y-m-d');
        $refundProportion = $refundAmount->toFloat() / $order->getTotalGross();
        $itemsByOccurrence = $this->groupItemsByOccurrence($order);

        foreach ($itemsByOccurrence as $occurrenceId => $items) {
            $existing = $this->eventOccurrenceDailyStatisticRepository->findFirstWhere([
                'event_occurrence_id' => $occurrenceId,
                'date' => $orderDate,
            ]);

            if (!$existing) {
                continue;
            }

            $occurrenceGross = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalGross() ?? 0, $items));
            $occurrenceTax = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalTax() ?? 0, $items));
            $occurrenceFee = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalServiceFee() ?? 0, $items));
            $occurrenceRefundAmount = $occurrenceGross * $refundProportion;

            $this->eventOccurrenceDailyStatisticRepository->updateWhere(
                attributes: [
                    'sales_total_gross' => max(0, $existing->getSalesTotalGross() - $occurrenceRefundAmount),
                    'total_refunded' => $existing->getTotalRefunded() + $occurrenceRefundAmount,
                    'total_tax' => max(0, $existing->getTotalTax() - ($occurrenceTax * $refundProportion)),
                    'total_fee' => max(0, $existing->getTotalFee() - ($occurrenceFee * $refundProportion)),
                ],
                where: [
                    'event_occurrence_id' => $occurrenceId,
                    'date' => $orderDate,
                ]
            );
        }
    }

    /**
     * @return array<int, OrderItemDomainObject[]>
     */
    private function groupItemsByOccurrence(OrderDomainObject $order): array
    {
        $itemsByOccurrence = [];
        foreach ($order->getOrderItems() as $orderItem) {
            $occId = $orderItem->getEventOccurrenceId();
            if ($occId === null) {
                continue;
            }
            $itemsByOccurrence[$occId][] = $orderItem;
        }
        return $itemsByOccurrence;
    }
}
