<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Exceptions\EventStatisticsVersionMismatchException;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Infrastructure\Utlitiy\Retry\Retrier;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Throwable;

class EventStatisticsIncrementService
{
    public function __construct(
        private readonly PromoCodeRepositoryInterface           $promoCodeRepository,
        private readonly ProductRepositoryInterface             $productRepository,
        private readonly EventStatisticRepositoryInterface      $eventStatisticsRepository,
        private readonly EventDailyStatisticRepositoryInterface      $eventDailyStatisticRepository,
        private readonly EventOccurrenceStatisticRepositoryInterface      $eventOccurrenceStatisticRepository,
        private readonly EventOccurrenceDailyStatisticRepositoryInterface $eventOccurrenceDailyStatisticRepository,
        private readonly DatabaseManager                                  $databaseManager,
        private readonly OrderRepositoryInterface               $orderRepository,
        private readonly LoggerInterface                        $logger,
        private readonly Retrier                                $retrier,
    )
    {
    }

    /**
     * Increment statistics for a new order
     *
     * @throws EventStatisticsVersionMismatchException
     * @throws Throwable
     */
    public function incrementForOrder(OrderDomainObject $order): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());

        $this->retrier->retry(
            callableAction: function () use ($order): void {
                $this->databaseManager->transaction(function () use ($order): void {
                    $this->incrementAggregateStatistics($order);
                    $this->incrementDailyStatistics($order);
                    $this->incrementOccurrenceStatistics($order);
                    $this->incrementOccurrenceDailyStatistics($order);
                    $this->incrementPromoCodeUsage($order);
                    $this->incrementProductStatistics($order);
                });
            },
            onFailure: function (int $attempt, Throwable $e) use ($order): void {
                $this->logger->error(
                    'Failed to increment event statistics for order after multiple attempts',
                    [
                        'event_id' => $order->getEventId(),
                        'order_id' => $order->getId(),
                        'attempts' => $attempt,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]
                );
            },
            retryOn: [EventStatisticsVersionMismatchException::class]
        );
    }

    /**
     * Increment aggregate event statistics
     *
     * @throws EventStatisticsVersionMismatchException
     */
    private function incrementAggregateStatistics(OrderDomainObject $order): void
    {
        $eventStatistics = $this->eventStatisticsRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
        ]);

        $productsSold = $order->getOrderItems()
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()) ?? 0;

        $attendeesRegistered = $order->getTicketOrderItems()
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()) ?? 0;

        if ($eventStatistics === null) {
            $this->eventStatisticsRepository->create([
                'event_id' => $order->getEventId(),
                'products_sold' => $productsSold,
                'attendees_registered' => $attendeesRegistered,
                'sales_total_gross' => $order->getTotalGross(),
                'sales_total_before_additions' => $order->getTotalBeforeAdditions(),
                'total_tax' => $order->getTotalTax(),
                'total_fee' => $order->getTotalFee(),
                'orders_created' => 1,
                'orders_cancelled' => 0,
            ]);

            $this->logger->info(
                'Event aggregate statistics created for new event',
                [
                    'event_id' => $order->getEventId(),
                    'order_id' => $order->getId(),
                    'products_sold' => $productsSold,
                    'attendees_registered' => $attendeesRegistered,
                ]
            );

            return;
        }

        $updates = [
            'products_sold' => $eventStatistics->getProductsSold() + $productsSold,
            'attendees_registered' => $eventStatistics->getAttendeesRegistered() + $attendeesRegistered,
            'sales_total_gross' => $eventStatistics->getSalesTotalGross() + $order->getTotalGross(),
            'sales_total_before_additions' => $eventStatistics->getSalesTotalBeforeAdditions() + $order->getTotalBeforeAdditions(),
            'total_tax' => $eventStatistics->getTotalTax() + $order->getTotalTax(),
            'total_fee' => $eventStatistics->getTotalFee() + $order->getTotalFee(),
            'orders_created' => $eventStatistics->getOrdersCreated() + 1,
            'version' => $eventStatistics->getVersion() + 1,
        ];

        $updated = $this->eventStatisticsRepository->updateWhere(
            attributes: $updates,
            where: [
                'event_id' => $order->getEventId(),
                'version' => $eventStatistics->getVersion(),
            ]
        );

        if ($updated === 0) {
            throw new EventStatisticsVersionMismatchException(
                'Event statistics version mismatch. Expected version '
                . $eventStatistics->getVersion() . ' but it was already updated.'
            );
        }

        $this->logger->info(
            'Event aggregate statistics incremented for order',
            [
                'event_id' => $order->getEventId(),
                'order_id' => $order->getId(),
                'products_sold' => $productsSold,
                'attendees_registered' => $attendeesRegistered,
                'new_version' => $eventStatistics->getVersion() + 1,
            ]
        );
    }

    /**
     * Increment daily event statistics
     *
     * @throws EventStatisticsVersionMismatchException
     */
    private function incrementDailyStatistics(OrderDomainObject $order): void
    {
        $orderDate = (new Carbon($order->getCreatedAt()))->format('Y-m-d');

        $eventDailyStatistic = $this->eventDailyStatisticRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
            'date' => $orderDate,
        ]);

        $productsSold = $order->getOrderItems()
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()) ?? 0;

        $attendeesRegistered = $order->getTicketOrderItems()
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()) ?? 0;

        if ($eventDailyStatistic === null) {
            $this->eventDailyStatisticRepository->create([
                'event_id' => $order->getEventId(),
                'date' => $orderDate,
                'products_sold' => $productsSold,
                'attendees_registered' => $attendeesRegistered,
                'sales_total_gross' => $order->getTotalGross(),
                'sales_total_before_additions' => $order->getTotalBeforeAdditions(),
                'total_tax' => $order->getTotalTax(),
                'total_fee' => $order->getTotalFee(),
                'orders_created' => 1,
                'orders_cancelled' => 0,
            ]);

            $this->logger->info(
                'Event daily statistics created for new date',
                [
                    'event_id' => $order->getEventId(),
                    'order_id' => $order->getId(),
                    'date' => $orderDate,
                    'products_sold' => $productsSold,
                    'attendees_registered' => $attendeesRegistered,
                ]
            );

            return;
        }

        $updates = [
            'products_sold' => $eventDailyStatistic->getProductsSold() + $productsSold,
            'attendees_registered' => $eventDailyStatistic->getAttendeesRegistered() + $attendeesRegistered,
            'sales_total_gross' => $eventDailyStatistic->getSalesTotalGross() + $order->getTotalGross(),
            'sales_total_before_additions' => $eventDailyStatistic->getSalesTotalBeforeAdditions() + $order->getTotalBeforeAdditions(),
            'total_tax' => $eventDailyStatistic->getTotalTax() + $order->getTotalTax(),
            'total_fee' => $eventDailyStatistic->getTotalFee() + $order->getTotalFee(),
            'orders_created' => $eventDailyStatistic->getOrdersCreated() + 1,
            'version' => $eventDailyStatistic->getVersion() + 1,
        ];

        $updated = $this->eventDailyStatisticRepository->updateWhere(
            attributes: $updates,
            where: [
                'event_id' => $order->getEventId(),
                'date' => $orderDate,
                'version' => $eventDailyStatistic->getVersion(),
            ],
        );

        if ($updated === 0) {
            throw new EventStatisticsVersionMismatchException(
                'Event daily statistics version mismatch. Expected version '
                . $eventDailyStatistic->getVersion() . ' but it was already updated.'
            );
        }

        $this->logger->info(
            'Event daily statistics incremented for order',
            [
                'event_id' => $order->getEventId(),
                'order_id' => $order->getId(),
                'date' => $orderDate,
                'products_sold' => $productsSold,
                'attendees_registered' => $attendeesRegistered,
                'new_version' => $eventDailyStatistic->getVersion() + 1,
            ]
        );
    }

    /**
     * Increment occurrence statistics, grouped by occurrence_id from order items
     *
     * @throws EventStatisticsVersionMismatchException
     */
    private function incrementOccurrenceStatistics(OrderDomainObject $order): void
    {
        $itemsByOccurrence = [];
        foreach ($order->getOrderItems() as $orderItem) {
            $occId = $orderItem->getEventOccurrenceId();
            if ($occId === null) {
                continue;
            }
            $itemsByOccurrence[$occId][] = $orderItem;
        }

        foreach ($itemsByOccurrence as $occurrenceId => $items) {
            $productsSold = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getQuantity(), $items));
            $attendeesRegistered = array_sum(array_map(
                fn(OrderItemDomainObject $i) => $i->getProductType() === ProductType::TICKET->name ? $i->getQuantity() : 0,
                $items,
            ));
            $totalGross = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalGross(), $items));
            $totalBeforeAdditions = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalBeforeAdditions(), $items));
            $totalTax = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalTax() ?? 0, $items));
            $totalFee = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalServiceFee() ?? 0, $items));

            $existing = $this->eventOccurrenceStatisticRepository->findFirstWhere([
                'event_id' => $order->getEventId(),
                'event_occurrence_id' => $occurrenceId,
            ]);

            if ($existing === null) {
                $this->eventOccurrenceStatisticRepository->create([
                    'event_id' => $order->getEventId(),
                    'event_occurrence_id' => $occurrenceId,
                    'products_sold' => $productsSold,
                    'attendees_registered' => $attendeesRegistered,
                    'sales_total_gross' => $totalGross,
                    'sales_total_before_additions' => $totalBeforeAdditions,
                    'total_tax' => $totalTax,
                    'total_fee' => $totalFee,
                    'orders_created' => 1,
                    'orders_cancelled' => 0,
                ]);
                continue;
            }

            $updates = [
                'products_sold' => $existing->getProductsSold() + $productsSold,
                'attendees_registered' => $existing->getAttendeesRegistered() + $attendeesRegistered,
                'sales_total_gross' => $existing->getSalesTotalGross() + $totalGross,
                'sales_total_before_additions' => $existing->getSalesTotalBeforeAdditions() + $totalBeforeAdditions,
                'total_tax' => $existing->getTotalTax() + $totalTax,
                'total_fee' => $existing->getTotalFee() + $totalFee,
                'orders_created' => $existing->getOrdersCreated() + 1,
                'version' => $existing->getVersion() + 1,
            ];

            $updated = $this->eventOccurrenceStatisticRepository->updateWhere(
                attributes: $updates,
                where: [
                    'event_occurrence_id' => $occurrenceId,
                    'version' => $existing->getVersion(),
                ]
            );

            if ($updated === 0) {
                throw new EventStatisticsVersionMismatchException(
                    'Occurrence statistics version mismatch for occurrence ' . $occurrenceId
                );
            }
        }
    }

    /**
     * @throws EventStatisticsVersionMismatchException
     */
    private function incrementOccurrenceDailyStatistics(OrderDomainObject $order): void
    {
        $orderDate = (new Carbon($order->getCreatedAt()))->format('Y-m-d');

        $itemsByOccurrence = [];
        foreach ($order->getOrderItems() as $orderItem) {
            $occId = $orderItem->getEventOccurrenceId();
            if ($occId === null) {
                continue;
            }
            $itemsByOccurrence[$occId][] = $orderItem;
        }

        foreach ($itemsByOccurrence as $occurrenceId => $items) {
            $productsSold = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getQuantity(), $items));
            $attendeesRegistered = array_sum(array_map(
                fn(OrderItemDomainObject $i) => $i->getProductType() === ProductType::TICKET->name ? $i->getQuantity() : 0,
                $items,
            ));
            $totalGross = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalGross(), $items));
            $totalBeforeAdditions = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalBeforeAdditions(), $items));
            $totalTax = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalTax() ?? 0, $items));
            $totalFee = array_sum(array_map(fn(OrderItemDomainObject $i) => $i->getTotalServiceFee() ?? 0, $items));

            $existing = $this->eventOccurrenceDailyStatisticRepository->findFirstWhere([
                'event_occurrence_id' => $occurrenceId,
                'date' => $orderDate,
            ]);

            if ($existing === null) {
                $this->eventOccurrenceDailyStatisticRepository->create([
                    'event_id' => $order->getEventId(),
                    'event_occurrence_id' => $occurrenceId,
                    'date' => $orderDate,
                    'products_sold' => $productsSold,
                    'attendees_registered' => $attendeesRegistered,
                    'sales_total_gross' => $totalGross,
                    'sales_total_before_additions' => $totalBeforeAdditions,
                    'total_tax' => $totalTax,
                    'total_fee' => $totalFee,
                    'orders_created' => 1,
                    'orders_cancelled' => 0,
                ]);
                continue;
            }

            $updates = [
                'products_sold' => $existing->getProductsSold() + $productsSold,
                'attendees_registered' => $existing->getAttendeesRegistered() + $attendeesRegistered,
                'sales_total_gross' => $existing->getSalesTotalGross() + $totalGross,
                'sales_total_before_additions' => $existing->getSalesTotalBeforeAdditions() + $totalBeforeAdditions,
                'total_tax' => $existing->getTotalTax() + $totalTax,
                'total_fee' => $existing->getTotalFee() + $totalFee,
                'orders_created' => $existing->getOrdersCreated() + 1,
                'version' => $existing->getVersion() + 1,
            ];

            $updated = $this->eventOccurrenceDailyStatisticRepository->updateWhere(
                attributes: $updates,
                where: [
                    'event_occurrence_id' => $occurrenceId,
                    'date' => $orderDate,
                    'version' => $existing->getVersion(),
                ]
            );

            if ($updated === 0) {
                throw new EventStatisticsVersionMismatchException(
                    'Occurrence daily statistics version mismatch for occurrence ' . $occurrenceId
                );
            }
        }
    }

    /**
     * Increment promo code usage counts
     */
    private function incrementPromoCodeUsage(OrderDomainObject $order): void
    {
        if ($order->getPromoCodeId() === null) {
            return;
        }

        $this->promoCodeRepository->increment(
            id: $order->getPromoCodeId(),
            column: PromoCodeDomainObjectAbstract::ORDER_USAGE_COUNT,
        );

        $attendeeCount = $order->getOrderItems()
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()) ?? 0;

        if ($attendeeCount > 0) {
            $this->promoCodeRepository->increment(
                id: $order->getPromoCodeId(),
                column: PromoCodeDomainObjectAbstract::ATTENDEE_USAGE_COUNT,
                amount: $attendeeCount,
            );
        }

        $this->logger->info(
            'Promo code usage incremented',
            [
                'promo_code_id' => $order->getPromoCodeId(),
                'order_id' => $order->getId(),
                'attendee_count' => $attendeeCount,
            ]
        );
    }

    /**
     * Increment product sales volume statistics
     */
    private function incrementProductStatistics(OrderDomainObject $order): void
    {
        foreach ($order->getOrderItems() as $orderItem) {
            $this->productRepository->increment(
                $orderItem->getProductId(),
                ProductDomainObjectAbstract::SALES_VOLUME,
                $orderItem->getTotalBeforeAdditions(),
            );
        }

        $this->logger->info(
            'Product sales volume incremented',
            [
                'order_id' => $order->getId(),
                'product_count' => $order->getOrderItems()->count(),
            ]
        );
    }
}
