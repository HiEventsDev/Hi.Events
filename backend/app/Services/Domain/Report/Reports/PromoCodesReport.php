<?php

namespace HiEvents\Services\Domain\Report\Reports;

use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Support\Carbon;

class PromoCodesReport extends AbstractReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate): string
    {
        $startDateString = $startDate->format('Y-m-d H:i:s');
        $endDateString = $endDate->format('Y-m-d H:i:s');

        return <<<SQL
                    WITH promo_metrics AS (
                        SELECT
                            COALESCE(pc.code, o.promo_code) as promo_code,
                            COUNT(DISTINCT o.id) as times_used,
                            COUNT(DISTINCT o.email) as unique_customers,
                            COALESCE(SUM(o.total_gross), 0) as total_gross_sales,
                            COALESCE(SUM(o.total_before_additions), 0) as total_before_discounts,
                            COALESCE(SUM(o.total_before_additions - o.total_gross), 0) as total_discount_amount,
                            CASE
                                WHEN COUNT(o.id) > 0 THEN ROUND(AVG(o.total_before_additions - o.total_gross)::numeric, 2)
                                ELSE 0
                            END as avg_discount_per_order,
                            CASE
                                WHEN COUNT(o.id) > 0 THEN ROUND(AVG(o.total_gross)::numeric, 2)
                                ELSE 0
                            END as avg_order_value,
                            MIN(o.created_at AT TIME ZONE 'UTC') as first_used_at,
                            MAX(o.created_at AT TIME ZONE 'UTC') as last_used_at,
                            pc.discount as configured_discount,
                            pc.discount_type,
                            pc.max_allowed_usages,
                            pc.expiry_date AT TIME ZONE 'UTC' as expiry_date,
                            CASE
                                WHEN pc.max_allowed_usages IS NOT NULL
                                    THEN pc.max_allowed_usages - COUNT(o.id)::integer
                            END as remaining_uses,
                            CASE
                                WHEN pc.expiry_date < CURRENT_TIMESTAMP THEN 'Expired'
                                WHEN pc.max_allowed_usages IS NOT NULL AND COUNT(o.id) >= pc.max_allowed_usages THEN 'Limit Reached'
                                WHEN pc.deleted_at IS NOT NULL THEN 'Deleted'
                                ELSE 'Active'
                            END as status
                        FROM promo_codes pc
                        LEFT JOIN orders o ON
                            pc.id = o.promo_code_id
                            AND o.deleted_at IS NULL
                            AND o.status NOT IN ('RESERVED')
                            AND o.event_id = :event_id
                            AND o.created_at >= '$startDateString'
                            AND o.created_at <= '$endDateString'
                        WHERE
                            pc.deleted_at IS NULL
                            AND pc.event_id = :event_id
                        GROUP BY
                            pc.id,
                            COALESCE(pc.code, o.promo_code),
                            pc.discount,
                            pc.discount_type,
                            pc.max_allowed_usages,
                            pc.expiry_date,
                            pc.deleted_at
                    )
                    SELECT
                        promo_code,
                        times_used,
                        unique_customers,
                        configured_discount,
                        discount_type,
                        total_gross_sales,
                        total_before_discounts,
                        total_discount_amount,
                        avg_discount_per_order,
                        avg_order_value,
                        first_used_at,
                        last_used_at,
                        max_allowed_usages,
                        remaining_uses,
                        expiry_date,
                        status
                    FROM promo_metrics
                    ORDER BY
                        total_gross_sales DESC,
                        promo_code;
        SQL;
    }
}
