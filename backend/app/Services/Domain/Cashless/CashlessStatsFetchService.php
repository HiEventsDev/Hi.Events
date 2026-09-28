<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\Services\Domain\Cashless\DTO\CashlessDailyStatsDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

readonly class CashlessStatsFetchService
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @return Collection<CashlessDailyStatsDTO>
     */
    public function getDailyStats(int $eventId, string $startDate, string $endDate): Collection
    {
        $topupOnline = CashlessTransactionType::TOPUP_ONLINE->value;
        $topupStaff = CashlessTransactionType::TOPUP_STAFF->value;
        $purchase = CashlessTransactionType::PURCHASE->value;
        $reversal = CashlessTransactionType::REVERSAL->value;
        $refund = CashlessTransactionType::REFUND_REMAINING->value;

        $query = <<<SQL
            WITH date_series AS (
                SELECT date::date
                FROM generate_series(:startDate::date, :endDate::date, '1 day') AS gs(date)
            ),
            movements AS (
                SELECT
                    t.created_at::date AS date,
                    CASE
                        WHEN t.type IN ('$topupOnline', '$topupStaff') THEN t.amount
                        WHEN t.type = '$reversal' AND r.type IN ('$topupOnline', '$topupStaff') THEN t.amount
                        ELSE 0
                    END AS topped_up,
                    CASE
                        WHEN t.type = '$purchase' THEN -t.amount
                        WHEN t.type = '$reversal' AND r.type = '$purchase' THEN -t.amount
                        ELSE 0
                    END AS spent,
                    CASE WHEN t.type = '$refund' THEN -t.amount ELSE 0 END AS refunded
                FROM cashless_transactions t
                         LEFT JOIN cashless_transactions r ON r.id = t.reverses_transaction_id
                WHERE t.event_id = :eventId
                  AND t.created_at::date >= :startDateFilter::date
                  AND t.created_at::date <= :endDateFilter::date
            )
            SELECT
                ds.date,
                COALESCE(SUM(m.topped_up), 0) AS topped_up,
                COALESCE(SUM(m.spent), 0) AS spent,
                COALESCE(SUM(m.refunded), 0) AS refunded
            FROM date_series ds
                     LEFT JOIN movements m ON m.date = ds.date
            GROUP BY ds.date
            ORDER BY ds.date ASC;
        SQL;

        $results = $this->db->select($query, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'startDateFilter' => $startDate,
            'endDateFilter' => $endDate,
            'eventId' => $eventId,
        ]);

        $currentTime = Carbon::now('UTC')->toTimeString();

        return collect($results)->map(static fn (object $row) => new CashlessDailyStatsDTO(
            date: (new Carbon($row->date))->format('Y-m-d').' '.$currentTime,
            topped_up: (float) $row->topped_up,
            spent: (float) $row->spent,
            refunded: (float) $row->refunded,
        ));
    }
}
