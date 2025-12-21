<?php

namespace HiEvents\Services\Domain\Report\OrganizerReports;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Domain\Report\AbstractOrganizerReportService;
use Illuminate\Support\Carbon;

class TaxSummaryReport extends AbstractOrganizerReportService
{
    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?string $currency = null): string
    {
        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();
        $completedStatus = OrderStatus::COMPLETED->name;
        $currencyFilter = $this->buildCurrencyFilter('o.currency', $currency);

        return <<<SQL
            WITH tax_data AS (
                SELECT
                    e.id AS event_id,
                    e.title AS event_name,
                    e.currency AS event_currency,
                    tax_item->>'name' AS tax_name,
                    (tax_item->>'rate')::numeric AS tax_rate,
                    (tax_item->>'value')::numeric AS tax_value
                FROM orders o
                INNER JOIN events e ON o.event_id = e.id
                CROSS JOIN LATERAL jsonb_array_elements(
                    COALESCE(o.taxes_and_fees_rollup->'taxes', '[]'::jsonb)
                ) AS tax_item
                WHERE e.organizer_id = :organizer_id
                    AND o.status = '$completedStatus'
                    AND o.deleted_at IS NULL
                    AND e.deleted_at IS NULL
                    $currencyFilter
                    AND DATE(o.created_at) BETWEEN '$startDateStr' AND '$endDateStr'
            )
            SELECT
                event_id,
                event_name,
                event_currency,
                tax_name,
                tax_rate,
                SUM(tax_value) AS total_collected,
                COUNT(*) AS order_count
            FROM tax_data
            GROUP BY event_id, event_name, event_currency, tax_name, tax_rate
            ORDER BY event_name, tax_name
SQL;
    }
}
