<?php

namespace HiEvents\Services\Domain\Report\Reports;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Support\Carbon;

class ProductSalesReport extends AbstractReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate): string
    {
        $startDateString = $startDate->format('Y-m-d H:i:s');
        $endDateString = $endDate->format('Y-m-d H:i:s');
        $completedStatus = OrderStatus::COMPLETED->name;

        return <<<SQL
        WITH filtered_orders AS (
            SELECT
                oi.product_id,
                oi.quantity,
                oi.total_tax,
                oi.total_gross,
                oi.total_service_fee
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status = '$completedStatus'
                AND o.event_id = :event_id
                AND o.created_at BETWEEN '$startDateString' AND '$endDateString'
                AND oi.deleted_at IS NULL
        )
        SELECT
            p.id AS product_id,
            p.title AS product_title,
            p.type AS product_type,
            COALESCE(SUM(fo.total_tax), 0) AS total_tax,
            COALESCE(SUM(fo.total_gross), 0) AS total_gross,
            COALESCE(SUM(fo.total_service_fee), 0) AS total_service_fees,
            COALESCE(SUM(fo.quantity), 0) AS number_sold
        FROM products p
        LEFT JOIN filtered_orders fo ON fo.product_id = p.id
        WHERE p.event_id = :event_id
            AND p.deleted_at IS NULL
        GROUP BY p.id, p.title, p.type
        ORDER BY p."order"
SQL;
    }
}
