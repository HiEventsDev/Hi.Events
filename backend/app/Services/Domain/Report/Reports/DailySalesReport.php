<?php

namespace HiEvents\Services\Domain\Report\Reports;

use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Support\Carbon;

class DailySalesReport extends AbstractReportService
{
    public function getSqlQuery(Carbon $startDate, Carbon $endDate): string
    {
        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();

        return <<<SQL
            WITH date_range AS (
                SELECT generate_series('$startDateStr'::date, '$endDateStr'::date, '1 day'::interval) AS date
            )
            SELECT
                d.date,
                COALESCE(eds.sales_total_gross, 0.00) AS sales_total_gross,
                COALESCE(eds.total_tax, 0.00) AS total_tax,
                COALESCE(eds.sales_total_before_additions, 0.00) AS sales_total_before_additions,
                COALESCE(eds.products_sold, 0) AS products_sold,
                COALESCE(eds.orders_created, 0) AS orders_created,
                COALESCE(eds.total_fee, 0.00) AS total_fee,
                COALESCE(eds.total_refunded, 0.00) AS total_refunded,
                COALESCE(eds.total_views, 0) AS total_views
            FROM
                date_range d
                LEFT JOIN event_daily_statistics eds
                    ON d.date = eds.date
                    AND eds.event_id = :event_id
            ORDER BY d.date desc;
SQL;
    }
}
