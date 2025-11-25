<?php

namespace HiEvents\Services\Domain\Report\OrganizerReports;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Report\AbstractOrganizerReportService;
use Illuminate\Support\Carbon;

class EventsPerformanceReport extends AbstractOrganizerReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?string $currency = null): string
    {
        $completedStatus = OrderStatus::COMPLETED->name;
        $orderCurrencyFilter = $this->buildCurrencyFilter('o.currency', $currency);
        $eventCurrencyFilter = $this->buildCurrencyFilter('e.currency', $currency);

        return <<<SQL
            WITH organizer_events AS (
                SELECT id
                FROM events
                WHERE organizer_id = :organizer_id
                    AND deleted_at IS NULL
            ),
            order_stats AS (
                SELECT
                    o.event_id,
                    SUM(o.total_gross) AS gross_revenue,
                    SUM(o.total_refunded) AS total_refunded,
                    SUM(o.total_gross - o.total_refunded) AS net_revenue,
                    COUNT(DISTINCT o.id) AS total_orders,
                    COUNT(DISTINCT o.email) AS unique_customers
                FROM orders o
                WHERE o.event_id IN (SELECT id FROM organizer_events)
                    AND o.status = '$completedStatus'
                    AND o.deleted_at IS NULL
                    $orderCurrencyFilter
                GROUP BY o.event_id
            ),
            product_stats AS (
                SELECT
                    o.event_id,
                    SUM(oi.quantity) AS products_sold
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE o.event_id IN (SELECT id FROM organizer_events)
                    AND o.status = '$completedStatus'
                    AND o.deleted_at IS NULL
                    AND oi.deleted_at IS NULL
                    $orderCurrencyFilter
                GROUP BY o.event_id
            )
            SELECT
                e.id AS event_id,
                e.title AS event_name,
                e.currency AS event_currency,
                e.start_date,
                e.end_date,
                e.status,
                CASE
                    WHEN e.end_date < NOW() THEN 'past'
                    WHEN e.start_date <= NOW() AND (e.end_date >= NOW() OR e.end_date IS NULL) THEN 'ongoing'
                    WHEN e.status = 'LIVE' THEN 'on_sale'
                    ELSE 'upcoming'
                END AS event_state,
                COALESCE(ps.products_sold, 0) AS products_sold,
                COALESCE(os.gross_revenue, 0.00) AS gross_revenue,
                COALESCE(os.total_refunded, 0.00) AS total_refunded,
                COALESCE(os.net_revenue, 0.00) AS net_revenue,
                COALESCE(es.total_tax, 0.00) AS total_tax,
                COALESCE(es.total_fee, 0.00) AS total_fee,
                COALESCE(os.total_orders, 0) AS total_orders,
                COALESCE(os.unique_customers, 0) AS unique_customers,
                COALESCE(es.total_views, 0) AS page_views
            FROM events e
            LEFT JOIN order_stats os ON e.id = os.event_id
            LEFT JOIN product_stats ps ON e.id = ps.event_id
            LEFT JOIN event_statistics es ON e.id = es.event_id
            WHERE e.organizer_id = :organizer_id
                AND e.deleted_at IS NULL
                $eventCurrencyFilter
            ORDER BY
                CASE
                    WHEN e.start_date IS NULL THEN 1
                    ELSE 0
                END,
                e.start_date DESC
SQL;
    }
}
