<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
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

class EventStatisticsCancellationService
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
     * Decrement statistics for a cancelled order (deterministic - only decrements once)
     *
     * @throws EventStatisticsVersionMismatchException
     * @throws Throwable
     */
    public function decrementForCancelledOrder(OrderDomainObject $order): void
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());

        // Check if statistics have already been decremented for this order
        if ($order->getStatisticsDecrementedAt() !== null) {
            $this->logger->info(
                'Statistics already decremented for cancelled order',
                [
                    'order_id' => $order->getId(),
                    'event_id' => $order->getEventId(),
                    'decremented_at' => $order->getStatisticsDecrementedAt(),
                ]
            );
            return;
        }

        $this->retrier->retry(
            callableAction: function (int $attempt) use ($order): void {
                $this->databaseManager->transaction(function () use ($order, $attempt): void {
                    $currentOrder = $this->orderRepository->findById($order->getId());
                    if ($currentOrder->getStatisticsDecrementedAt() !== null) {
                        $this->logger->info(
                            'Statistics already decremented for cancelled order (checked within transaction)',
                            [
                                'order_id' => $order->getId(),
                                'event_id' => $order->getEventId(),
                                'decremented_at' => $currentOrder->getStatisticsDecrementedAt(),
                            ]
                        );
                        return;
                    }

                    // Calculate counts to decrement
                    $counts = $this->calculateDecrementCounts($order);

                    // Decrement aggregate statistics
                    $this->decrementAggregateStatistics($order, $counts, $attempt);

                    // Decrement daily statistics
                    $this->decrementDailyStatistics($order, $counts, $attempt);

                    // Mark statistics as decremented
                    $this->markStatisticsAsDecremented($order);
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
     * Decrement statistics for a cancelled attendee
     *
     * @throws EventStatisticsVersionMismatchException
     * @throws Throwable
     */
    public function decrementForCancelledAttendee(int $eventId, string $orderDate, int $attendeeCount = 1): void
    {
        $this->retrier->retry(
            callableAction: function () use ($eventId, $orderDate, $attendeeCount): void {
                $this->databaseManager->transaction(function () use ($eventId, $orderDate, $attendeeCount): void {
                    // Decrement aggregate statistics
                    $this->decrementAggregateAttendeeStatistics($eventId, $attendeeCount);

                    // Decrement daily statistics
                    $this->decrementDailyAttendeeStatistics($eventId, $orderDate, $attendeeCount);
                });
            },
            onFailure: function (int $attempt, Throwable $e) use ($eventId, $orderDate, $attendeeCount): void {
                $this->logger->error(
                    'Failed to decrement event statistics for cancelled attendee after multiple attempts',
                    [
                        'event_id' => $eventId,
                        'order_date' => $orderDate,
                        'attendee_count' => $attendeeCount,
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
        // Get attendees that are currently active or awaiting payment (not already cancelled)
        $activeAttendees = $this->attendeeRepository->findWhereIn(
            field: 'status',
            values: [AttendeeStatus::ACTIVE->name, AttendeeStatus::AWAITING_PAYMENT->name],
            additionalWhere: ['order_id' => $order->getId()],
        );

        $activeAttendeeCount = $activeAttendees->count();

        // Products sold should be the full order quantities - products don't get "uncancelled"
        // when individual attendees are cancelled, only when the entire order is cancelled
        $productsSold = $order->getOrderItems()
            ?->sum(fn(OrderItemDomainObject $orderItem) => $orderItem->getQuantity()) ?? 0;

        // Attendees registered should only be the currently active attendees
        // to avoid over-decrementing when some attendees were already cancelled individually
        $attendeesRegistered = $activeAttendeeCount;

        return [
            'active_attendees' => $activeAttendeeCount,
            'products_sold' => $productsSold,
            'attendees_registered' => $attendeesRegistered,
        ];
    }

    /**
     * Decrement aggregate event statistics for cancelled order
     *
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
            'orders_cancelled' => ($eventStatistics->getOrdersCancelled() ?? 0) + 1,
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
                'orders_cancelled_total' => ($eventStatistics->getOrdersCancelled() ?? 0) + 1,
                'attempt' => $attempt,
                'new_version' => $eventStatistics->getVersion() + 1,
            ]
        );
    }

    /**
     * Decrement aggregate event statistics for cancelled attendee
     *
     * @throws EventStatisticsVersionMismatchException
     */
    private function decrementAggregateAttendeeStatistics(int $eventId, int $attendeeCount): void
    {
        $eventStatistics = $this->eventStatisticsRepository->findFirstWhere([
            'event_id' => $eventId,
        ]);

        if (!$eventStatistics) {
            throw new ResourceNotFoundException('Event statistics not found for event ' . $eventId);
        }

        // Only decrement attendees_registered for individual attendee cancellations
        // products_sold should NOT be affected as the product was still sold
        $updates = [
            'attendees_registered' => max(0, $eventStatistics->getAttendeesRegistered() - $attendeeCount),
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
            'Event aggregate statistics decremented for cancelled attendee',
            [
                'event_id' => $eventId,
                'attendees_decremented' => $attendeeCount,
                'products_affected' => 0, // Products sold not affected by individual attendee cancellations
                'new_version' => $eventStatistics->getVersion() + 1,
            ]
        );
    }

    /**
     * Decrement daily event statistics for cancelled order
     *
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
            'orders_cancelled' => ($eventDailyStatistic->getOrdersCancelled() ?? 0) + 1,
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
                'orders_cancelled_total' => ($eventDailyStatistic->getOrdersCancelled() ?? 0) + 1,
                'attempt' => $attempt,
                'new_version' => $eventDailyStatistic->getVersion() + 1,
            ]
        );
    }

    /**
     * Decrement daily event statistics for cancelled attendee
     *
     * @throws EventStatisticsVersionMismatchException
     */
    private function decrementDailyAttendeeStatistics(int $eventId, string $orderDate, int $attendeeCount): void
    {
        $formattedDate = (new Carbon($orderDate))->format('Y-m-d');

        $eventDailyStatistic = $this->eventDailyStatisticRepository->findFirstWhere([
            'event_id' => $eventId,
            'date' => $formattedDate,
        ]);

        if (!$eventDailyStatistic) {
            $this->logger->warning(
                'Event daily statistics not found for event, skipping daily decrement for cancelled attendee',
                [
                    'event_id' => $eventId,
                    'date' => $formattedDate,
                ]
            );
            return;
        }

        // Only decrement attendees_registered for individual attendee cancellations
        // products_sold should NOT be affected as the product was still sold
        $updates = [
            'attendees_registered' => max(0, $eventDailyStatistic->getAttendeesRegistered() - $attendeeCount),
            'version' => $eventDailyStatistic->getVersion() + 1,
        ];

        $updated = $this->eventDailyStatisticRepository->updateWhere(
            attributes: $updates,
            where: [
                'event_id' => $eventId,
                'date' => $formattedDate,
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
            'Event daily statistics decremented for cancelled attendee',
            [
                'event_id' => $eventId,
                'date' => $formattedDate,
                'attendees_decremented' => $attendeeCount,
                'products_affected' => 0, // Products sold not affected by individual attendee cancellations
                'new_version' => $eventDailyStatistic->getVersion() + 1,
            ]
        );
    }

    /**
     * Mark that statistics have been decremented for this order
     */
    private function markStatisticsAsDecremented(OrderDomainObject $order): void
    {
        $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::STATISTICS_DECREMENTED_AT => now(),
        ]);

        $this->logger->info(
            'Order marked as statistics decremented',
            [
                'order_id' => $order->getId(),
                'event_id' => $order->getEventId(),
                'decremented_at' => now()->toIso8601String(),
            ]
        );
    }
}
