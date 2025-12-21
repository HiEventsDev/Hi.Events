<?php

namespace HiEvents\Services\Domain\Report\OrganizerReports;

use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Services\Domain\Report\AbstractOrganizerReportService;
use Illuminate\Support\Carbon;

class CheckInSummaryReport extends AbstractOrganizerReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?string $currency = null): string
    {
        $activeStatus = AttendeeStatus::ACTIVE->name;

        return <<<SQL
            WITH organizer_events AS (
                SELECT id
                FROM events
                WHERE organizer_id = :organizer_id
                    AND deleted_at IS NULL
            )
            SELECT
                e.id AS event_id,
                e.title AS event_name,
                e.start_date,
                COALESCE(attendee_counts.total_attendees, 0) AS total_attendees,
                COALESCE(checkin_counts.total_checked_in, 0) AS total_checked_in,
                CASE
                    WHEN COALESCE(attendee_counts.total_attendees, 0) = 0 THEN 0
                    ELSE ROUND((COALESCE(checkin_counts.total_checked_in, 0)::numeric / attendee_counts.total_attendees) * 100, 1)
                END AS check_in_rate,
                COALESCE(list_counts.check_in_lists_count, 0) AS check_in_lists_count
            FROM events e
            LEFT JOIN (
                SELECT
                    event_id,
                    COUNT(*) AS total_attendees
                FROM attendees
                WHERE event_id IN (SELECT id FROM organizer_events)
                    AND status = '$activeStatus'
                    AND deleted_at IS NULL
                GROUP BY event_id
            ) attendee_counts ON e.id = attendee_counts.event_id
            LEFT JOIN (
                SELECT
                    event_id,
                    COUNT(DISTINCT attendee_id) AS total_checked_in
                FROM attendee_check_ins
                WHERE event_id IN (SELECT id FROM organizer_events)
                    AND deleted_at IS NULL
                GROUP BY event_id
            ) checkin_counts ON e.id = checkin_counts.event_id
            LEFT JOIN (
                SELECT
                    event_id,
                    COUNT(*) AS check_in_lists_count
                FROM check_in_lists
                WHERE event_id IN (SELECT id FROM organizer_events)
                    AND deleted_at IS NULL
                GROUP BY event_id
            ) list_counts ON e.id = list_counts.event_id
            WHERE e.organizer_id = :organizer_id
                AND e.deleted_at IS NULL
            ORDER BY e.start_date DESC NULLS LAST
SQL;
    }
}
