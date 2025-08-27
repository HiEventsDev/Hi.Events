<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\EventStatisticsVersionMismatchException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\Utlitiy\Retry\Retrier;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class EventStatisticsDecrementService
{
    public function __construct(
        private readonly EventStatisticRepositoryInterface      $eventStatisticsRepository,
        private readonly EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        private readonly AttendeeRepositoryInterface            $attendeeRepository,
        private readonly OrderRepositoryInterface               $orderRepository,
        private readonly LoggerInterface                        $logger,
        private readonly DatabaseManager                        $databaseManager,
        private readonly Retrier                                $retrier,
    )
    {
    }

    /**
     * Decrements statistics for a cancelled order, including both aggregate and daily statistics
     *
     * @throws EventStatisticsVersionMismatchException
     * @throws Throwable
     */
    public function decrementStatisticsForCancelledOrder(OrderDomainObject $order): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());

        $this->retrier->retry(
            callableAction: function (int $attempt) use ($order): void {
                $this->databaseManager->transaction(function () use ($order, $attempt): void {
                    // Calculate counts that need to be decremented
                    $counts = $this->calculateDecrementCounts($order);

                    // Update aggregate statistics
                    $this->decrementAggregateStatistics($order, $counts, $attempt);

                    // Update daily statistics
                    $this->decrementDailyStatistics($order, $counts, $attempt);
                });
            },
            onFailure: function (int $attempt, Throwable $e) use ($order): void {
                $this->logger->error(
                    'Failed to decrement event statistics for cancelled order after multiple attempts',
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
     * Calculate the counts that need to be decremented from statistics
     */
    private function calculateDecrementCounts(OrderDomainObject $order): array
    {
        $activeAttendeeCount = $this->attendeeRepository->findWhereIn(
            field: 'status',
            values: [AttendeeStatus::ACTIVE->name, AttendeeStatus::AWAITING_PAYMENT->name],
            additionalWhere: ['order_id' => $order->getId()],
        )->count();

        // Count products sold from order items
        $productsSold = $order->getOrderItems()
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()) ?? 0;

        // Count attendees registered (ticket items only)
        $attendeesRegistered = $order->getTicketOrderItems()
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()) ?? 0;

        return [
            'active_attendees' => $activeAttendeeCount,
            'products_sold' => $productsSold,
            'attendees_registered' => $attendeesRegistered,
        ];
    }

    /**
     * Decrement aggregate event statistics
     * @throws EventStatisticsVersionMismatchException
     */
    private function decrementAggregateStatistics(OrderDomainObject $order, array $counts, int $attempt): void
    {
        $eventStatistics = $this->eventStatisticsRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
        ]);

        if (!$eventStatistics) {
            throw new ResourceNotFoundException('Event statistics not found for event ' . $order->getEventId());
        }

        $updates = [
            'attendees_registered' => max(0, $eventStatistics->getAttendeesRegistered() - $counts['attendees_registered']),
            'products_sold' => max(0, $eventStatistics->getProductsSold() - $counts['products_sold']),
            'orders_created' => max(0, $eventStatistics->getOrdersCreated() - 1),
            'version' => $eventStatistics->getVersion() + 1,
        ];

        $updated = $this->eventStatisticsRepository->updateWhere(
            attributes: $updates,
            where: [
                'id' => $eventStatistics->getId(),
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
            'Event aggregate statistics decremented for cancelled order',
            [
                'event_id' => $order->getEventId(),
                'order_id' => $order->getId(),
                'attendees_decremented' => $counts['attendees_registered'],
                'products_decremented' => $counts['products_sold'],
                'attempt' => $attempt,
                'new_version' => $eventStatistics->getVersion() + 1,
            ]
        );
    }

    /**
     * @throws EventStatisticsVersionMismatchException
     */
    private function decrementDailyStatistics(OrderDomainObject $order, array $counts, int $attempt): void
    {
        $orderDate = (new Carbon($order->getCreatedAt()))->format('Y-m-d');

        $eventDailyStatistic = $this->eventDailyStatisticRepository->findFirstWhere([
            'event_id' => $order->getEventId(),
            'date' => $orderDate,
        ]);

        if (!$eventDailyStatistic) {
            // If daily statistics don't exist, we can skip this
            $this->logger->warning(
                'Event daily statistics not found for event, skipping daily decrement',
                [
                    'event_id' => $order->getEventId(),
                    'date' => $orderDate,
                ]
            );
            return;
        }

        $updates = [
            'attendees_registered' => max(0, $eventDailyStatistic->getAttendeesRegistered() - $counts['attendees_registered']),
            'products_sold' => max(0, $eventDailyStatistic->getProductsSold() - $counts['products_sold']),
            'orders_created' => max(0, $eventDailyStatistic->getOrdersCreated() - 1),
            'version' => $eventDailyStatistic->getVersion() + 1,
        ];

        $updated = $this->eventDailyStatisticRepository->updateWhere(
            attributes: $updates,
            where: [
                'event_id' => $order->getEventId(),
                'date' => $orderDate,
                'version' => $eventDailyStatistic->getVersion(),
            ]
        );

        if ($updated === 0) {
            throw new EventStatisticsVersionMismatchException(
                'Event daily statistics version mismatch. Expected version '
                . $eventDailyStatistic->getVersion() . ' but it was already updated.'
            );
        }

        $this->logger->info(
            'Event daily statistics decremented for cancelled order',
            [
                'event_id' => $order->getEventId(),
                'order_id' => $order->getId(),
                'date' => $orderDate,
                'attendees_decremented' => $counts['attendees_registered'],
                'products_decremented' => $counts['products_sold'],
                'attempt' => $attempt,
                'new_version' => $eventDailyStatistic->getVersion() + 1,
            ]
        );
    }
}
