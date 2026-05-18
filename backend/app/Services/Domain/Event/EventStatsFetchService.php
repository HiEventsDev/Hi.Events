<?php

namespace HiEvents\Services\Domain\Event;

use Carbon\Carbon;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\EventStatsRequestDTO;
use HiEvents\Services\Application\Handlers\Event\DTO\EventStatsResponseDTO;
use HiEvents\Services\Domain\Event\DTO\EventCheckInStatsResponseDTO;
use HiEvents\Services\Domain\Event\DTO\EventDailyStatsResponseDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

readonly class EventStatsFetchService
{
    public function __construct(
        private DatabaseManager $db,
        private EventRepositoryInterface $eventRepository,
    ) {}

    public function getEventStats(EventStatsRequestDTO $requestData): EventStatsResponseDTO
    {
        if ($requestData->start_date === null || $requestData->end_date === null) {
            [$startDate, $endDate] = $this->resolveStatsDateRange(
                $requestData->event_id,
                $requestData->date_range_preset
            );
            $requestData->start_date = $startDate;
            $requestData->end_date = $endDate;
        }

        $eventId = $requestData->event_id;
        $occurrenceId = $requestData->occurrence_id;

        if ($occurrenceId !== null) {
            // event_id is bound here so an organiser with access to event A
            // cannot pass an occurrence id belonging to event B and read its
            // stats. Action-level authorization gates eventId; this keeps the
            // query honest about that scope.
            $totalsQuery = <<<'SQL'
            SELECT
                COALESCE(SUM(eods.products_sold), 0) AS total_products_sold,
                COALESCE(SUM(eods.orders_created), 0) AS total_orders,
                COALESCE(SUM(eods.sales_total_gross), 0) AS total_gross_sales,
                COALESCE(SUM(eods.total_tax), 0) AS total_tax,
                COALESCE(SUM(eods.total_fee), 0) AS total_fees,
                0 AS total_views,
                COALESCE(SUM(eods.total_refunded), 0) AS total_refunded,
                COALESCE(SUM(eods.attendees_registered), 0) AS attendees_registered
            FROM event_occurrence_daily_statistics eods
            WHERE eods.event_occurrence_id = :occurrenceId
              AND eods.event_id = :eventId
              AND eods.deleted_at IS NULL
              AND eods.date >= :startDate::date
              AND eods.date <= :endDate::date;
            SQL;
            $totalsResult = $this->db->selectOne($totalsQuery, [
                'occurrenceId' => $occurrenceId,
                'eventId' => $eventId,
                'startDate' => $requestData->start_date,
                'endDate' => $requestData->end_date,
            ]);
        } else {
            $totalsQuery = <<<'SQL'
            SELECT
                COALESCE(SUM(eods.products_sold), 0) AS total_products_sold,
                COALESCE(SUM(eods.orders_created), 0) AS total_orders,
                COALESCE(SUM(eods.sales_total_gross), 0) AS total_gross_sales,
                COALESCE(SUM(eods.total_tax), 0) AS total_tax,
                COALESCE(SUM(eods.total_fee), 0) AS total_fees,
                COALESCE((
                    SELECT SUM(eds.total_views)
                    FROM event_daily_statistics eds
                    WHERE eds.event_id = :eventIdViews
                      AND eds.deleted_at IS NULL
                      AND eds.date >= :startDateViews::date
                      AND eds.date <= :endDateViews::date
                ), 0) AS total_views,
                COALESCE(SUM(eods.total_refunded), 0) AS total_refunded,
                COALESCE(SUM(eods.attendees_registered), 0) AS attendees_registered
            FROM event_occurrence_daily_statistics eods
            WHERE eods.event_id = :eventId
              AND eods.deleted_at IS NULL
              AND eods.date >= :startDate::date
              AND eods.date <= :endDate::date;
            SQL;
            $totalsResult = $this->db->selectOne($totalsQuery, [
                'eventId' => $eventId,
                'eventIdViews' => $eventId,
                'startDate' => $requestData->start_date,
                'endDate' => $requestData->end_date,
                'startDateViews' => $requestData->start_date,
                'endDateViews' => $requestData->end_date,
            ]);
        }

        return new EventStatsResponseDTO(
            daily_stats: $this->getDailyEventStats($requestData),
            start_date: $requestData->start_date,
            end_date: $requestData->end_date,
            total_products_sold: $totalsResult->total_products_sold ?? 0,
            total_attendees_registered: $totalsResult->attendees_registered ?? 0,
            total_orders: $totalsResult->total_orders ?? 0,
            total_gross_sales: $totalsResult->total_gross_sales ?? 0,
            total_fees: $totalsResult->total_fees ?? 0,
            total_tax: $totalsResult->total_tax ?? 0,
            total_views: $totalsResult->total_views ?? 0,
            total_refunded: $totalsResult->total_refunded ?? 0,
        );
    }

    public function getDailyEventStats(EventStatsRequestDTO $requestData): Collection
    {
        $eventId = $requestData->event_id;
        $occurrenceId = $requestData->occurrence_id;
        $startDate = $requestData->start_date;
        $endDate = $requestData->end_date;

        if ($occurrenceId !== null) {
            // event_id is bound alongside occurrence_id so cross-event ids
            // produce zero rows rather than another event's stats.
            $whereClause = 'eods.event_occurrence_id = :occurrenceId AND eods.event_id = :eventId';
            $bindings = ['startDate' => $startDate, 'endDate' => $endDate, 'occurrenceId' => $occurrenceId, 'eventId' => $eventId];
        } else {
            $whereClause = 'eods.event_id = :eventId';
            $bindings = ['startDate' => $startDate, 'endDate' => $endDate, 'eventId' => $eventId];
        }

        $query = <<<SQL
            WITH date_series AS (
              SELECT date::date
              FROM generate_series(
                :startDate::date,
                :endDate::date,
                '1 day'
              ) AS gs(date)
            )
            SELECT
              ds.date,
              COALESCE(SUM(eods.total_fee), 0) AS total_fees,
              COALESCE(SUM(eods.total_tax), 0) AS total_tax,
              COALESCE(SUM(eods.sales_total_gross), 0) AS total_sales_gross,
              COALESCE(SUM(eods.orders_created), 0) AS orders_created,
              COALESCE(SUM(eods.products_sold), 0) AS products_sold,
              COALESCE(SUM(eods.attendees_registered), 0) AS attendees_registered,
              COALESCE(SUM(eods.total_refunded), 0) AS total_refunded
            FROM date_series ds
            LEFT JOIN event_occurrence_daily_statistics eods ON ds.date = eods.date AND eods.deleted_at IS NULL AND {$whereClause}
            GROUP BY ds.date
            ORDER BY ds.date ASC;
        SQL;

        $results = $this->db->select($query, $bindings);

        $currentTime = Carbon::now('UTC')->toTimeString();

        return collect($results)->map(function (object $result) use ($currentTime) {
            $dateTimeWithCurrentTime = (new Carbon($result->date))->setTimezone('UTC')->format('Y-m-d').' '.$currentTime;

            return new EventDailyStatsResponseDTO(
                date: $dateTimeWithCurrentTime,
                total_fees: $result->total_fees,
                total_tax: $result->total_tax,
                total_sales_gross: $result->total_sales_gross,
                products_sold: $result->products_sold,
                orders_created: $result->orders_created,
                attendees_registered: $result->attendees_registered,
                total_refunded: $result->total_refunded,
            );
        });
    }

    private function resolveStatsDateRange(int $eventId, string $preset): array
    {
        $event = $this->eventRepository->findById($eventId);

        $bounds = $this->db->selectOne(
            'SELECT MIN(date) as min_date, MAX(date) as max_date
             FROM event_daily_statistics
             WHERE event_id = :eventId AND deleted_at IS NULL',
            ['eventId' => $eventId]
        );

        $candidates = array_filter([
            $event->getStartDate() ? Carbon::parse($event->getStartDate()) : null,
            $bounds?->min_date ? Carbon::parse($bounds->min_date) : null,
        ]);
        $adjustedStart = $candidates ? min($candidates) : Carbon::now()->subDays(7);

        switch ($preset) {
            case 'week':
                $endDate = (clone $adjustedStart)->addDays(7);
                break;
            case 'month':
                $endDate = (clone $adjustedStart)->addDays(30);
                break;
            case 'quarter':
                $endDate = (clone $adjustedStart)->addDays(90);
                break;
            case 'event':
                $eventEnd = $event->getEndDate() ? Carbon::parse($event->getEndDate()) : null;
                $endCandidates = array_filter([
                    $eventEnd,
                    $bounds?->max_date ? Carbon::parse($bounds->max_date) : null,
                    (! $eventEnd || $eventEnd->isFuture()) ? Carbon::now() : null,
                ]);
                $endDate = $endCandidates ? max($endCandidates) : Carbon::now();
                break;
            default: // 'last_30_days'
                $adjustedStart = Carbon::now()->subDays(30);
                $endDate = Carbon::now();
        }

        return [
            $adjustedStart->format('Y-m-d H:i:s'),
            $endDate->format('Y-m-d H:i:s'),
        ];
    }

    public function getCheckedInStats(int $eventId, ?int $occurrenceId = null): EventCheckInStatsResponseDTO
    {
        $bindings = ['eventId' => $eventId];

        $occurrenceFilter = '';
        if ($occurrenceId !== null) {
            $occurrenceFilter = 'AND attendees.event_occurrence_id = :occurrenceId';
            $bindings['occurrenceId'] = $occurrenceId;
        }

        $query = <<<SQL
            SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN attendees.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in_count
            FROM attendees
            INNER JOIN orders ON orders.id = attendees.order_id
            WHERE orders.event_id = :eventId
              AND orders.status = 'COMPLETED'
              AND attendees.status = 'ACTIVE'
              {$occurrenceFilter};
        SQL;

        $result = $this->db->select($query, $bindings)[0];

        return new EventCheckInStatsResponseDTO(
            total_checked_in_attendees: $result->checked_in_count ?? 0,
            total_attendees: $result->total_count ?? 0,
        );
    }
}
