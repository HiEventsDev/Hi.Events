<?php

namespace HiEvents\Services\Domain\Report\Reports;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Support\Carbon;

class AttendeesByProductReport extends AbstractReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate): string
    {
        $startDateString = $startDate->format('Y-m-d H:i:s');
        $endDateString = $endDate->format('Y-m-d H:i:s');
        $completedStatus = OrderStatus::COMPLETED->name;

        return <<<SQL
        SELECT
            p.id AS product_id,
            p.title AS product_title,
            p.type AS product_type,
            a.id AS attendee_id,
            a.short_id AS attendee_short_id,
            a.first_name,
            a.last_name,
            a.email,
            a.public_id AS attendee_public_id,
            a.status AS attendee_status,
            pp.label AS price_label,
            pp.price AS ticket_price,
            o.short_id AS order_short_id,
            o.id AS order_id,
            o.created_at AS order_date,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM attendee_check_ins aci
                    WHERE aci.attendee_id = a.id AND aci.deleted_at IS NULL
                ) THEN true
                ELSE false
            END AS is_checked_in,
            (
                SELECT MAX(aci.created_at)
                FROM attendee_check_ins aci
                WHERE aci.attendee_id = a.id AND aci.deleted_at IS NULL
            ) AS last_check_in_at
        FROM attendees a
        JOIN orders o ON a.order_id = o.id
        JOIN products p ON a.product_id = p.id
        LEFT JOIN product_prices pp ON a.product_price_id = pp.id
        WHERE o.status = '$completedStatus'
            AND a.event_id = :event_id
            AND o.created_at BETWEEN '$startDateString' AND '$endDateString'
            AND a.deleted_at IS NULL
            AND o.deleted_at IS NULL
        ORDER BY p.title ASC, a.last_name ASC, a.first_name ASC
SQL;
    }
}
