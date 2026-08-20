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
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EventStatisticsRefundService
{
    public function __construct(
        private readonly EventStatisticRepositoryInterface $eventStatisticsRepository,
        private readonly EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        private readonly EventOccurrenceStatisticRepositoryInterface $eventOccurrenceStatisticRepository,
        private readonly EventOccurrenceDailyStatisticRepositoryInterface $eventOccurrenceDailyStatisticRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Update statistics when an order is refunded
     */
    public function updateForRefund(OrderDomainObject $order, MoneyValue $refundAmount): void
    {
        $this->updateAggregateStatisticsForRefund($order, $refundAmount);
        $this->updateDailyStatisticsForRefund($order, $refundAmount);

        $orderWithItems = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());

        if ($orderWithItems->getTotalGross() <= 0) {
            return;
        }

        $itemsByOccurrence = $this->groupItemsByOccurrence($orderWithItems);
        if (empty($itemsByOccurrence)) {
            return;
        }

        $refundProportion = $refundAmount->toFloat() / $orderWithItems->getTotalGross();
        $refundDate = now()->format('Y-m-d');

        $this->updateOccurrenceStatisticsForRefund($itemsByOccurrence, $refundProportion);
        $this->updateOccurrenceDailyStatisticsForRefund($orderWithItems->getEventId(), $itemsByOccurrence, $refundProportion, $refundDate);
    }

    /**
     * Update aggregate statistics for a refund
     */
    private function updateAggregateStatisticsForRefund(OrderDomainObject $order, MoneyValue $refundAmount): void
    {
        $eventStatistics = $this->eventStatisticsRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
        ]);

        if (! $eventStatistics) {
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
        $refundDate = now()->format('Y-m-d');

        $eventDailyStatistic = $this->eventDailyStatisticRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
            'date' => $refundDate,
        ]);

        if ($eventDailyStatistic === null) {
            $this->eventDailyStatisticRepository->create([
                'event_id' => $order->getEventId(),
                'date' => $refundDate,
                'products_sold' => 0,
                'attendees_registered' => 0,
                'sales_total_gross' => -$refundAmount->toFloat(),
                'sales_total_before_additions' => 0,
                'total_tax' => 0,
                'total_fee' => 0,
                'total_refunded' => $refundAmount->toFloat(),
                'orders_created' => 0,
                'orders_cancelled' => 0,
            ]);

            return;
        }

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
                'date' => $refundDate,
            ]
        );

        $this->logger->info(
            'Event daily statistics updated for refund',
            [
                'event_id' => $order->getEventId(),
                'order_id' => $order->getId(),
                'date' => $refundDate,
                'refund_amount' => $refundAmount->toFloat(),
                'refund_proportion' => $refundProportion,
                'original_total_gross' => $eventDailyStatistic->getSalesTotalGross(),
                'original_total_refunded' => $eventDailyStatistic->getTotalRefunded(),
                'tax_adjustment' => $order->getTotalTax() * $refundProportion,
                'fee_adjustment' => $order->getTotalFee() * $refundProportion,
            ]
        );
    }

    /**
     * @param  array<int, OrderItemDomainObject[]>  $itemsByOccurrence
     */
    private function updateOccurrenceStatisticsForRefund(array $itemsByOccurrence, float $refundProportion): void
    {
        foreach ($itemsByOccurrence as $occurrenceId => $items) {
            $occurrenceGross = array_sum(array_map(fn (OrderItemDomainObject $i) => $i->getTotalGross() ?? 0, $items));
            $occurrenceTax = array_sum(array_map(fn (OrderItemDomainObject $i) => $i->getTotalTax() ?? 0, $items));
            $occurrenceFee = array_sum(array_map(fn (OrderItemDomainObject $i) => $i->getTotalServiceFee() ?? 0, $items));

            $grossDelta = $this->formatDelta($occurrenceGross * $refundProportion);
            $taxDelta = $this->formatDelta($occurrenceTax * $refundProportion);
            $feeDelta = $this->formatDelta($occurrenceFee * $refundProportion);

            $this->eventOccurrenceStatisticRepository->updateWhere(
                attributes: [
                    'sales_total_gross' => DB::raw("GREATEST(0, sales_total_gross - {$grossDelta})"),
                    'total_refunded' => DB::raw("total_refunded + {$grossDelta}"),
                    'total_tax' => DB::raw("GREATEST(0, total_tax - {$taxDelta})"),
                    'total_fee' => DB::raw("GREATEST(0, total_fee - {$feeDelta})"),
                    'version' => DB::raw('version + 1'),
                ],
                where: [
                    'event_occurrence_id' => $occurrenceId,
                ]
            );
        }
    }

    /**
     * @param  array<int, OrderItemDomainObject[]>  $itemsByOccurrence
     */
    private function updateOccurrenceDailyStatisticsForRefund(int $eventId, array $itemsByOccurrence, float $refundProportion, string $refundDate): void
    {
        foreach ($itemsByOccurrence as $occurrenceId => $items) {
            $occurrenceGross = array_sum(array_map(fn (OrderItemDomainObject $i) => $i->getTotalGross() ?? 0, $items));
            $occurrenceTax = array_sum(array_map(fn (OrderItemDomainObject $i) => $i->getTotalTax() ?? 0, $items));
            $occurrenceFee = array_sum(array_map(fn (OrderItemDomainObject $i) => $i->getTotalServiceFee() ?? 0, $items));

            $grossRefund = $occurrenceGross * $refundProportion;

            $existing = $this->eventOccurrenceDailyStatisticRepository->findFirstWhere([
                'event_occurrence_id' => $occurrenceId,
                'date' => $refundDate,
            ]);

            if ($existing === null) {
                $this->eventOccurrenceDailyStatisticRepository->create([
                    'event_id' => $eventId,
                    'event_occurrence_id' => $occurrenceId,
                    'date' => $refundDate,
                    'products_sold' => 0,
                    'attendees_registered' => 0,
                    'sales_total_gross' => 0,
                    'sales_total_before_additions' => 0,
                    'total_tax' => 0,
                    'total_fee' => 0,
                    'total_refunded' => $grossRefund,
                    'orders_created' => 0,
                    'orders_cancelled' => 0,
                ]);

                continue;
            }

            $grossDelta = $this->formatDelta($grossRefund);
            $taxDelta = $this->formatDelta($occurrenceTax * $refundProportion);
            $feeDelta = $this->formatDelta($occurrenceFee * $refundProportion);

            $this->eventOccurrenceDailyStatisticRepository->updateWhere(
                attributes: [
                    'sales_total_gross' => DB::raw("GREATEST(0, sales_total_gross - {$grossDelta})"),
                    'total_refunded' => DB::raw("total_refunded + {$grossDelta}"),
                    'total_tax' => DB::raw("GREATEST(0, total_tax - {$taxDelta})"),
                    'total_fee' => DB::raw("GREATEST(0, total_fee - {$feeDelta})"),
                    'version' => DB::raw('version + 1'),
                ],
                where: [
                    'event_occurrence_id' => $occurrenceId,
                    'date' => $refundDate,
                ]
            );
        }
    }

    private function formatDelta(float $value): string
    {
        return number_format($value, 4, '.', '');
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
