<?php

namespace HiEvents\Services\Domain\Report\Reports;

use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Support\Carbon;

class CashlessSalesReport extends AbstractReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?int $occurrenceId = null): string
    {
        $startDateString = $startDate->format('Y-m-d H:i:s');
        $endDateString = $endDate->format('Y-m-d H:i:s');
        $purchase = CashlessTransactionType::PURCHASE->value;
        $reversal = CashlessTransactionType::REVERSAL->value;

        return <<<SQL
            WITH sales AS (
                SELECT
                    t.id,
                    t.cashless_sales_point_id,
                    t.created_at,
                    CASE WHEN t.type = '$purchase' THEN 1 ELSE -1 END AS direction,
                    COALESCE(t.reverses_transaction_id, t.id) AS items_source_id,
                    -t.amount AS net_amount
                FROM cashless_transactions t
                         LEFT JOIN cashless_transactions r ON r.id = t.reverses_transaction_id
                WHERE t.event_id = :event_id
                  AND t.created_at >= '$startDateString'
                  AND t.created_at <= '$endDateString'
                  AND (t.type = '$purchase' OR (t.type = '$reversal' AND r.type = '$purchase'))
            ),
            item_lines AS (
                SELECT
                    s.cashless_sales_point_id,
                    i.product_title,
                    SUM(i.quantity * s.direction) AS quantity_sold,
                    SUM(i.total * s.direction) AS product_revenue
                FROM sales s
                         JOIN cashless_transaction_items i ON i.cashless_transaction_id = s.items_source_id
                GROUP BY s.cashless_sales_point_id, i.product_title
            )
            SELECT
                COALESCE(sp.name, '-') AS sales_point,
                il.product_title,
                il.quantity_sold,
                ROUND(il.product_revenue, 2) AS product_revenue,
                sp_totals.transaction_count,
                ROUND(sp_totals.sales_point_revenue, 2) AS sales_point_revenue
            FROM item_lines il
                     LEFT JOIN cashless_sales_points sp ON sp.id = il.cashless_sales_point_id
                     LEFT JOIN (
                         SELECT
                             cashless_sales_point_id,
                             COUNT(*) FILTER (WHERE direction = 1) AS transaction_count,
                             SUM(net_amount) AS sales_point_revenue
                         FROM sales
                         GROUP BY cashless_sales_point_id
                     ) sp_totals ON sp_totals.cashless_sales_point_id = il.cashless_sales_point_id
            WHERE il.quantity_sold <> 0
            ORDER BY sales_point, product_revenue DESC;
        SQL;
    }
}
