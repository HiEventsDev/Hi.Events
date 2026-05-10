<?php

namespace HiEvents\Services\Domain\Report\Reports;

use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Support\Carbon;

class OccurrenceSummaryReport extends AbstractReportService
{
    protected function getAdditionalBindings(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
        ];
    }

    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?int $occurrenceId = null): string
    {
        return <<<SQL
            SELECT
                eo.id AS occurrence_id,
                eo.short_id,
                eo.start_date,
                eo.end_date,
                eo.status,
                eo.label,
                eo.capacity,
                eo.used_capacity,
                COALESCE(eos.products_sold, 0) AS products_sold,
                COALESCE(eos.attendees_registered, 0) AS attendees_registered,
                COALESCE(eos.orders_created, 0) AS orders_created,
                COALESCE(eos.sales_total_gross, 0) AS total_gross,
                COALESCE(eos.total_tax, 0) AS total_tax,
                COALESCE(eos.total_fee, 0) AS total_fee,
                COALESCE(eos.total_refunded, 0) AS total_refunded,
                COALESCE(ci_stats.checked_in, 0) AS checked_in
            FROM event_occurrences eo
            LEFT JOIN event_occurrence_statistics eos
                ON eos.event_occurrence_id = eo.id
            LEFT JOIN (
                SELECT event_occurrence_id, COUNT(*) AS checked_in
                FROM attendee_check_ins
                WHERE deleted_at IS NULL
                GROUP BY event_occurrence_id
            ) ci_stats ON ci_stats.event_occurrence_id = eo.id
            WHERE eo.event_id = :event_id
                AND eo.deleted_at IS NULL
                AND eo.start_date >= :start_date
                AND eo.start_date <= :end_date
            ORDER BY eo.start_date
SQL;
    }
}
