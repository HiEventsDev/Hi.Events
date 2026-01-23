<?php

namespace HiEvents\Services\Domain\Report\OrganizerReports;

use HiEvents\Services\Domain\Report\AbstractOrganizerReportService;
use Illuminate\Support\Carbon;

class RevenueSummaryReport extends AbstractOrganizerReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?string $currency = null): string
    {
        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();
        $currencyFilter = $this->buildCurrencyFilter('e.currency', $currency);

        return <<<SQL
            WITH date_range AS (
                SELECT generate_series('$startDateStr'::date, '$endDateStr'::date, '1 day'::interval) AS date
            ),
            daily_stats AS (
                SELECT
                    eds.date,
                    SUM(eds.sales_total_gross) AS gross_sales,
                    SUM(eds.total_refunded) AS total_refunded,
                    SUM(eds.sales_total_gross - eds.total_refunded) AS net_revenue,
                    SUM(eds.total_tax) AS total_tax,
                    SUM(eds.total_fee) AS total_fee,
                    SUM(eds.orders_created) AS order_count
                FROM event_daily_statistics eds
                INNER JOIN events e ON eds.event_id = e.id
                WHERE e.organizer_id = :organizer_id
                    AND e.deleted_at IS NULL
                    AND eds.deleted_at IS NULL
                    $currencyFilter
                    AND eds.date BETWEEN '$startDateStr' AND '$endDateStr'
                GROUP BY eds.date
            )
            SELECT
                d.date,
                COALESCE(ds.gross_sales, 0.00) AS gross_sales,
                COALESCE(ds.net_revenue, 0.00) AS net_revenue,
                COALESCE(ds.total_refunded, 0.00) AS total_refunded,
                COALESCE(ds.total_tax, 0.00) AS total_tax,
                COALESCE(ds.total_fee, 0.00) AS total_fee,
                COALESCE(ds.order_count, 0) AS order_count
            FROM date_range d
            LEFT JOIN daily_stats ds ON d.date = ds.date
            ORDER BY d.date DESC
SQL;
    }
}
