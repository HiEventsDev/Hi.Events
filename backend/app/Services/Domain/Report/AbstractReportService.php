<?php

namespace HiEvents\Services\Domain\Report;

use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

abstract class AbstractReportService
{
    public function __construct(
        private readonly Repository               $cache,
        private readonly DatabaseManager          $queryBuilder,
        private readonly EventRepositoryInterface $eventRepository,
    )
    {
    }

    public function generateReport(int $eventId, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $event = $this->eventRepository->findById($eventId);
        $timezone = $event->getTimezone();

        $endDate = Carbon::parse($endDate ?? now(), $timezone);
        $startDate = Carbon::parse($startDate ?? $endDate->copy()->subDays(30), $timezone);

        $reportResults = $this->cache->remember(
            key: $this->getCacheKey($eventId, $startDate, $endDate),
            ttl: Carbon::now()->addSeconds(20),
            callback: fn() => $this->queryBuilder->select(
                $this->getSqlQuery($startDate, $endDate),
                [
                    'event_id' => $eventId,
                ]
            )
        );

        return collect($reportResults);
    }

    abstract protected function getSqlQuery(Carbon $startDate, Carbon $endDate): string;

    protected function getCacheKey(int $eventId, ?Carbon $startDate, ?Carbon $endDate): string
    {
        return static::class . "$eventId.{$startDate?->toDateString()}.{$endDate?->toDateString()}";
    }
}
