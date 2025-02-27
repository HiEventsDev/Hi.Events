<?php

namespace HiEvents\Services\Domain\Report\Reports;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Support\Carbon;

class PromoCodesReport extends AbstractReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate): string
    {
        $startDateString = $startDate->format('Y-m-d H:i:s');
        $endDateString = $endDate->format('Y-m-d H:i:s');
        $reservedString = OrderStatus::RESERVED->name;
        $completedStatus = OrderStatus::COMPLETED->name;

        $translatedStringMap = [
            'Expired' => __('Expired'),
            'Limit Reached' => __('Limit Reached'),
            'Deleted' => __('Deleted'),
            'Active' => __('Active'),
        ];

        return <<<SQL
                WITH order_totals AS (
                    SELECT
                        o.id as order_id,
                        o.promo_code_id,
                        o.promo_code,
                        SUM(oi.price * oi.quantity) as original_total,
                        SUM(oi.price_before_discount * oi.quantity) as discounted_total,
                        o.total_gross,
                        o.email,
                        o.created_at
                    FROM orders o
                             JOIN order_items oi ON oi.order_id = o.id
                    WHERE
                        o.deleted_at IS NULL
                      AND o.status IN ('$completedStatus')
                      AND o.event_id = :event_id
                      AND o.created_at >= '$startDateString'
                      AND o.created_at <= '$endDateString'

                    GROUP BY
                        o.id,
                        o.promo_code_id,
                        o.promo_code,
                        o.total_gross,
                        o.email,
                        o.created_at
        ),
             promo_metrics AS (
                 SELECT
                     COALESCE(pc.code, ot.promo_code) as promo_code,
                     COUNT(DISTINCT ot.order_id) as times_used,
                     COUNT(DISTINCT ot.email) as unique_customers,
                     COALESCE(SUM(ot.total_gross), 0) as total_gross_sales,
                     COALESCE(SUM(ot.original_total), 0) as total_before_discounts,
                     COALESCE(SUM(ot.original_total - ot.discounted_total), 0) as total_discount_amount,
                     MIN(ot.created_at AT TIME ZONE 'UTC') as first_used_at,
                     MAX(ot.created_at AT TIME ZONE 'UTC') as last_used_at,
                     pc.discount as configured_discount,
                     pc.discount_type,
                     pc.max_allowed_usages,
                     pc.expiry_date AT TIME ZONE 'UTC' as expiry_date,
                     CASE
                         WHEN pc.max_allowed_usages IS NOT NULL
                             THEN pc.max_allowed_usages - COUNT(ot.order_id)::integer
                         END as remaining_uses,
                     CASE
                         WHEN pc.expiry_date < CURRENT_TIMESTAMP THEN '{$translatedStringMap['Expired']}'
                         WHEN pc.max_allowed_usages IS NOT NULL AND COUNT(ot.order_id) >= pc.max_allowed_usages THEN '{$translatedStringMap['Limit Reached']}'
                         WHEN pc.deleted_at IS NOT NULL THEN '{$translatedStringMap['Deleted']}'
                         ELSE '{$translatedStringMap['Active']}'
                         END as status
                 FROM promo_codes pc
                          LEFT JOIN order_totals ot ON pc.id = ot.promo_code_id
                 WHERE
                     pc.deleted_at IS NULL
                   AND pc.event_id = :event_id
                 GROUP BY
                     pc.id,
                     COALESCE(pc.code, ot.promo_code),
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
