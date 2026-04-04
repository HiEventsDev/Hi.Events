<?php

namespace HiEvents\Services\Domain\Event;

use Carbon\Carbon;
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
    )
    {
    }

    public function getEventStats(EventStatsRequestDTO $requestData): EventStatsResponseDTO
    {
        $eventId = $requestData->event_id;
        $occurrenceId = $requestData->occurrence_id;

        if ($occurrenceId !== null) {
            $totalsQuery = <<<SQL
            SELECT
                COALESCE(SUM(eos.products_sold), 0) AS total_products_sold,
                COALESCE(SUM(eos.orders_created), 0) AS total_orders,
                COALESCE(SUM(eos.sales_total_gross), 0) AS total_gross_sales,
                COALESCE(SUM(eos.total_tax), 0) AS total_tax,
                COALESCE(SUM(eos.total_fee), 0) AS total_fees,
                0 AS total_views,
                COALESCE(SUM(eos.total_refunded), 0) AS total_refunded,
                COALESCE(SUM(eos.attendees_registered), 0) AS attendees_registered
            FROM event_occurrence_statistics eos
            WHERE eos.event_occurrence_id = :occurrenceId
              AND eos.deleted_at IS NULL;
            SQL;
            $totalsResult = $this->db->selectOne($totalsQuery, ['occurrenceId' => $occurrenceId]);
        } else {
            $totalsQuery = <<<SQL
            SELECT
                COALESCE(SUM(eos.products_sold), 0) AS total_products_sold,
                COALESCE(SUM(eos.orders_created), 0) AS total_orders,
                COALESCE(SUM(eos.sales_total_gross), 0) AS total_gross_sales,
                COALESCE(SUM(eos.total_tax), 0) AS total_tax,
                COALESCE(SUM(eos.total_fee), 0) AS total_fees,
                COALESCE((SELECT SUM(es.total_views) FROM event_statistics es WHERE es.event_id = :eventIdViews AND es.deleted_at IS NULL), 0) AS total_views,
                COALESCE(SUM(eos.total_refunded), 0) AS total_refunded,
                COALESCE(SUM(eos.attendees_registered), 0) AS attendees_registered
            FROM event_occurrence_statistics eos
            WHERE eos.event_id = :eventId
              AND eos.deleted_at IS NULL;
            SQL;
            $totalsResult = $this->db->selectOne($totalsQuery, ['eventId' => $eventId, 'eventIdViews' => $eventId]);
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
            $whereClause = 'eods.event_occurrence_id = :occurrenceId';
            $bindings = ['startDate' => $startDate, 'endDate' => $endDate, 'occurrenceId' => $occurrenceId];
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
            $dateTimeWithCurrentTime = (new Carbon($result->date))->setTimezone('UTC')->format('Y-m-d') . ' ' . $currentTime;

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
