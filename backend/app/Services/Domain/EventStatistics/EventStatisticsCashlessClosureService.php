<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventStatistics;

use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\RepositoryInterface;

class EventStatisticsCashlessClosureService
{
    public function __construct(
        private readonly EventStatisticRepositoryInterface $eventStatisticsRepository,
        private readonly EventDailyStatisticRepositoryInterface $eventDailyStatisticRepository,
        private readonly EventOccurrenceStatisticRepositoryInterface $eventOccurrenceStatisticRepository,
        private readonly EventOccurrenceDailyStatisticRepositoryInterface $eventOccurrenceDailyStatisticRepository,
    ) {}

    public function recordClosedBalance(int $eventId, ?int $occurrenceId, string $date, float $amount): void
    {
        $this->add($this->eventStatisticsRepository, ['event_id' => $eventId], $amount);
        $this->add($this->eventDailyStatisticRepository, ['event_id' => $eventId, 'date' => $date], $amount);

        if ($occurrenceId === null) {
            return;
        }

        $this->add(
            $this->eventOccurrenceStatisticRepository,
            ['event_id' => $eventId, 'event_occurrence_id' => $occurrenceId],
            $amount,
        );
        $this->add(
            $this->eventOccurrenceDailyStatisticRepository,
            ['event_id' => $eventId, 'event_occurrence_id' => $occurrenceId, 'date' => $date],
            $amount,
        );
    }

    private function add(RepositoryInterface $repository, array $identity, float $amount): void
    {
        $updated = $repository->incrementEach(
            columns: ['sales_total_gross' => $amount, 'sales_total_before_additions' => $amount, 'version' => 1],
            where: $identity,
        );

        if ($updated === 0) {
            $repository->create([
                ...$identity,
                'sales_total_gross' => $amount,
                'sales_total_before_additions' => $amount,
            ]);
        }
    }
}
