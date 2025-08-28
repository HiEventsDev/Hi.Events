<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Values\MoneyValue;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class EventStatisticsRefundService
{
    public function __construct(
        private readonly EventStatisticRepositoryInterface      $eventStatisticsRepository,
        private readonly EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        private readonly LoggerInterface                        $logger,
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
}
