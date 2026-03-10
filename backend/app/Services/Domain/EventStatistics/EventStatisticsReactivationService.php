<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventStatistics;

use HiEvents\Exceptions\EventStatisticsVersionMismatchException;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Services\Infrastructure\Utlitiy\Retry\Retrier;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class EventStatisticsReactivationService
{
    public function __construct(
        private readonly EventStatisticRepositoryInterface      $eventStatisticsRepository,
        private readonly EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        private readonly LoggerInterface                        $logger,
        private readonly DatabaseManager                        $databaseManager,
        private readonly Retrier                                $retrier,
    )
    {
    }

    /**
     * @throws EventStatisticsVersionMismatchException
     * @throws Throwable
     */
    public function incrementForReactivatedAttendee(int $eventId, string $orderDate, int $attendeeCount = 1): void
    {
        $this->retrier->retry(
            callableAction: function () use ($eventId, $orderDate, $attendeeCount): void {
                $this->databaseManager->transaction(function () use ($eventId, $orderDate, $attendeeCount): void {
                    $this->incrementAggregateAttendeeStatistics($eventId, $attendeeCount);
                    $this->incrementDailyAttendeeStatistics($eventId, $orderDate, $attendeeCount);
                });
            },
            onFailure: function (int $attempt, Throwable $e) use ($eventId, $orderDate, $attendeeCount): void {
                $this->logger->error(
                    'Failed to increment event statistics for reactivated attendee after multiple attempts',
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
     * @throws EventStatisticsVersionMismatchException
     */
    private function incrementAggregateAttendeeStatistics(int $eventId, int $attendeeCount): void
    {
        $eventStatistics = $this->eventStatisticsRepository->findFirstWhere([
            'event_id' => $eventId,
        ]);

        if (!$eventStatistics) {
            throw new ResourceNotFoundException('Event statistics not found for event ' . $eventId);
        }

        $updates = [
            'attendees_registered' => $eventStatistics->getAttendeesRegistered() + $attendeeCount,
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
            'Event aggregate statistics incremented for reactivated attendee',
            [
                'event_id' => $eventId,
                'attendees_incremented' => $attendeeCount,
                'new_version' => $eventStatistics->getVersion() + 1,
            ]
        );
    }

    /**
     * @throws EventStatisticsVersionMismatchException
     */
    private function incrementDailyAttendeeStatistics(int $eventId, string $orderDate, int $attendeeCount): void
    {
        $formattedDate = (new Carbon($orderDate))->format('Y-m-d');

        $eventDailyStatistic = $this->eventDailyStatisticRepository->findFirstWhere([
            'event_id' => $eventId,
            'date' => $formattedDate,
        ]);

        if (!$eventDailyStatistic) {
            $this->logger->warning(
                'Event daily statistics not found for event, skipping daily increment for reactivated attendee',
                [
                    'event_id' => $eventId,
                    'date' => $formattedDate,
                ]
            );
            return;
        }

        $updates = [
            'attendees_registered' => $eventDailyStatistic->getAttendeesRegistered() + $attendeeCount,
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
            'Event daily statistics incremented for reactivated attendee',
            [
                'event_id' => $eventId,
                'date' => $formattedDate,
                'attendees_incremented' => $attendeeCount,
                'new_version' => $eventDailyStatistic->getVersion() + 1,
            ]
        );
    }
}
