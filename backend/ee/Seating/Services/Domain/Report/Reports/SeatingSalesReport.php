<?php

namespace HiEvents\Enterprise\Seating\Services\Domain\Report\Reports;

use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\SeatClaimStatus;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Report\AbstractReportService;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

class SeatingSalesReport extends AbstractReportService
{
    public function __construct(
        Repository $cache,
        DatabaseManager $queryBuilder,
        EventRepositoryInterface $eventRepository,
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
    ) {
        parent::__construct($cache, $queryBuilder, $eventRepository);
    }

    protected function getSqlQuery(Carbon $startDate, Carbon $endDate, ?int $occurrenceId = null): string
    {
        $liveClaims = $this->seatClaimRepository->liveClaimsWithStatusSql();
        $occurrenceFilter = $occurrenceId !== null ? 'AND eo.id = :occurrence_id' : '';
        $cancelled = EventOccurrenceStatus::CANCELLED->name;
        $completed = OrderStatus::COMPLETED->name;
        $sold = SeatClaimStatus::SOLD->name;
        $held = SeatClaimStatus::HELD->name;
        $blocked = SeatClaimStatus::BLOCKED->name;

        return <<<SQL
        WITH dates AS (
            SELECT eo.id
            FROM event_occurrences eo
            WHERE eo.event_id = :event_id
                AND eo.deleted_at IS NULL
                AND eo.status <> '$cancelled'
                $occurrenceFilter
        ),
        layout AS (
            SELECT esm.layout
            FROM event_seat_maps esm
            WHERE esm.event_id = :event_id
        ),
        bands AS (
            SELECT band.value->>'key' AS band_key, band.value->>'name' AS band_name, band.position AS band_position
            FROM layout
            CROSS JOIN LATERAL jsonb_array_elements(layout.layout->'bands') WITH ORDINALITY AS band(value, position)
        ),
        areas AS (
            SELECT area.value->>'id' AS area_id, area.value->>'name' AS area_name, area.position AS area_position, area.value->'elements' AS elements
            FROM layout
            CROSS JOIN LATERAL jsonb_array_elements(layout.layout->'areas') WITH ORDINALITY AS area(value, position)
        ),
        places AS (
            SELECT areas.area_id, seat->>'uid' AS seat_uid, seat->>'band' AS band_key, 1 AS capacity
            FROM areas
            CROSS JOIN LATERAL jsonb_array_elements(areas.elements) AS element
            CROSS JOIN LATERAL jsonb_array_elements(element->'seats') AS seat
            WHERE element->>'type' IN ('row', 'block', 'table')
            UNION ALL
            SELECT areas.area_id, element->>'id', element->>'band', CAST(element->>'capacity' AS integer)
            FROM areas
            CROSS JOIN LATERAL jsonb_array_elements(areas.elements) AS element
            WHERE element->>'type' = 'zone'
        ),
        live_claims AS (
            $liveClaims
        ),
        claim_counts AS (
            SELECT
                live_claims.seat_uid,
                live_claims.band_key,
                COUNT(*) FILTER (WHERE live_claims.status = '$sold') AS sold,
                COUNT(*) FILTER (WHERE live_claims.status = '$held') AS held,
                COUNT(*) FILTER (WHERE live_claims.status = '$blocked') AS blocked
            FROM live_claims
            JOIN dates ON dates.id = live_claims.event_occurrence_id
            WHERE live_claims.event_id = :event_id
            GROUP BY live_claims.seat_uid, live_claims.band_key
        ),
        facts AS (
            SELECT places.band_key, places.area_id, places.capacity * (SELECT COUNT(*) FROM dates) AS capacity, 0 AS sold, 0 AS held, 0 AS blocked
            FROM places
            UNION ALL
            SELECT claim_counts.band_key, places.area_id, 0, claim_counts.sold, claim_counts.held, claim_counts.blocked
            FROM claim_counts
            JOIN places ON places.seat_uid = claim_counts.seat_uid
        ),
        totals AS (
            SELECT
                band_key,
                area_id,
                GROUPING(area_id) = 1 AS is_band_total,
                SUM(capacity) AS capacity,
                SUM(sold) AS sold,
                SUM(held) AS held,
                SUM(blocked) AS blocked
            FROM facts
            GROUP BY GROUPING SETS ((band_key, area_id), (band_key))
        ),
        revenue AS (
            SELECT oi.band_key, SUM(oi.total_gross) AS total_gross
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN dates ON dates.id = oi.event_occurrence_id
            WHERE o.event_id = :event_id
                AND o.status = '$completed'
                AND o.deleted_at IS NULL
                AND oi.deleted_at IS NULL
                AND oi.band_key IS NOT NULL
            GROUP BY oi.band_key
        )
        SELECT
            totals.band_key,
            COALESCE(bands.band_name, totals.band_key) AS band_name,
            totals.area_id,
            areas.area_name,
            totals.is_band_total,
            CAST(totals.capacity AS integer) AS capacity,
            CAST(totals.sold AS integer) AS sold,
            CAST(totals.held AS integer) AS held,
            CAST(totals.blocked AS integer) AS blocked,
            CAST(GREATEST(totals.capacity - totals.sold - totals.held - totals.blocked, 0) AS integer) AS free,
            CASE WHEN totals.is_band_total THEN COALESCE(revenue.total_gross, 0) END AS total_gross
        FROM totals
        LEFT JOIN bands ON bands.band_key = totals.band_key
        LEFT JOIN areas ON areas.area_id = totals.area_id
        LEFT JOIN revenue ON revenue.band_key = totals.band_key AND totals.is_band_total
        ORDER BY bands.band_position NULLS LAST, totals.band_key, totals.is_band_total, areas.area_position
SQL;
    }
}
