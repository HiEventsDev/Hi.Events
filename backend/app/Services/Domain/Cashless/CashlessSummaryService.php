<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Services\Domain\Cashless\DTO\CashlessProductSummaryDTO;
use HiEvents\Services\Domain\Cashless\DTO\CashlessSalesPointSummaryDTO;
use HiEvents\Services\Domain\Cashless\DTO\CashlessSummaryDTO;
use Illuminate\Database\DatabaseManager;

readonly class CashlessSummaryService
{
    private const TOP_PRODUCTS_LIMIT = 10;

    public function __construct(private DatabaseManager $db) {}

    public function getSummary(int $eventId): CashlessSummaryDTO
    {
        $wallets = $this->fetchWalletTotals($eventId);
        $movements = $this->fetchMovements($eventId);

        return new CashlessSummaryDTO(
            wallets_total: (int) $wallets->wallets_total,
            wallets_with_balance: (int) $wallets->wallets_with_balance,
            wallets_frozen: (int) $wallets->wallets_frozen,
            wallets_closed: (int) $wallets->wallets_closed,
            outstanding_balance: (float) $wallets->outstanding_balance,
            topped_up_online: (float) $movements->topped_up_online,
            topped_up_staff: (float) $movements->topped_up_staff,
            spent: (float) $movements->spent,
            refunded: (float) $movements->refunded,
            closed: (float) $movements->closed,
            purchases_count: (int) $movements->purchases_count,
            sales_points: $this->fetchSalesPoints($eventId),
            top_products: $this->fetchTopProducts($eventId),
        );
    }

    private function fetchWalletTotals(int $eventId): object
    {
        $frozen = CashlessWalletStatus::FROZEN->value;
        $closed = CashlessWalletStatus::CLOSED->value;

        return $this->db->selectOne(<<<SQL
            SELECT
                COUNT(*) AS wallets_total,
                COUNT(*) FILTER (WHERE balance > 0) AS wallets_with_balance,
                COUNT(*) FILTER (WHERE status = '$frozen') AS wallets_frozen,
                COUNT(*) FILTER (WHERE status = '$closed') AS wallets_closed,
                COALESCE(SUM(balance), 0) AS outstanding_balance
            FROM cashless_wallets
            WHERE event_id = :eventId AND deleted_at IS NULL
        SQL, ['eventId' => $eventId]);
    }

    private function fetchMovements(int $eventId): object
    {
        $online = CashlessTransactionType::TOPUP_ONLINE->value;
        $staff = CashlessTransactionType::TOPUP_STAFF->value;
        $purchase = CashlessTransactionType::PURCHASE->value;
        $reversal = CashlessTransactionType::REVERSAL->value;
        $refund = CashlessTransactionType::REFUND_REMAINING->value;
        $closure = CashlessTransactionType::CLOSURE->value;

        return $this->db->selectOne(<<<SQL
            SELECT
                COALESCE(SUM(CASE WHEN t.type = '$online' OR (t.type = '$reversal' AND r.type = '$online') THEN t.amount ELSE 0 END), 0) AS topped_up_online,
                COALESCE(SUM(CASE WHEN t.type = '$staff' OR (t.type = '$reversal' AND r.type = '$staff') THEN t.amount ELSE 0 END), 0) AS topped_up_staff,
                COALESCE(SUM(CASE WHEN t.type = '$purchase' OR (t.type = '$reversal' AND r.type = '$purchase') THEN -t.amount ELSE 0 END), 0) AS spent,
                COALESCE(SUM(CASE WHEN t.type = '$refund' THEN -t.amount ELSE 0 END), 0) AS refunded,
                COALESCE(SUM(CASE WHEN t.type = '$closure' THEN -t.amount ELSE 0 END), 0) AS closed,
                COALESCE(SUM(CASE WHEN t.type = '$purchase' THEN 1 WHEN t.type = '$reversal' AND r.type = '$purchase' THEN -1 ELSE 0 END), 0) AS purchases_count
            FROM cashless_transactions t
                LEFT JOIN cashless_transactions r ON r.id = t.reverses_transaction_id
            WHERE t.event_id = :eventId
        SQL, ['eventId' => $eventId]);
    }

    /**
     * @return array<CashlessSalesPointSummaryDTO>
     */
    private function fetchSalesPoints(int $eventId): array
    {
        $staff = CashlessTransactionType::TOPUP_STAFF->value;
        $purchase = CashlessTransactionType::PURCHASE->value;
        $reversal = CashlessTransactionType::REVERSAL->value;

        $rows = $this->db->select(<<<SQL
            SELECT
                sp.name,
                COALESCE(SUM(CASE WHEN t.type = '$purchase' OR (t.type = '$reversal' AND r.type = '$purchase') THEN -t.amount ELSE 0 END), 0) AS spent,
                COALESCE(SUM(CASE WHEN t.type = '$purchase' THEN 1 WHEN t.type = '$reversal' AND r.type = '$purchase' THEN -1 ELSE 0 END), 0) AS purchases_count,
                COALESCE(SUM(CASE WHEN t.type = '$staff' OR (t.type = '$reversal' AND r.type = '$staff') THEN t.amount ELSE 0 END), 0) AS topped_up
            FROM cashless_transactions t
                LEFT JOIN cashless_transactions r ON r.id = t.reverses_transaction_id
                JOIN cashless_sales_points sp ON sp.id = t.cashless_sales_point_id
            WHERE t.event_id = :eventId
            GROUP BY sp.id, sp.name
            ORDER BY spent DESC, sp.name ASC
        SQL, ['eventId' => $eventId]);

        return array_map(static fn (object $row) => new CashlessSalesPointSummaryDTO(
            name: $row->name,
            spent: (float) $row->spent,
            purchases_count: (int) $row->purchases_count,
            topped_up: (float) $row->topped_up,
        ), $rows);
    }

    /**
     * @return array<CashlessProductSummaryDTO>
     */
    private function fetchTopProducts(int $eventId): array
    {
        $purchase = CashlessTransactionType::PURCHASE->value;

        $rows = $this->db->select(<<<SQL
            SELECT i.product_title AS title, SUM(i.quantity) AS quantity, SUM(i.total) AS total
            FROM cashless_transaction_items i
                JOIN cashless_transactions t ON t.id = i.cashless_transaction_id
            WHERE t.event_id = :eventId
              AND t.type = '$purchase'
              AND NOT EXISTS (SELECT 1 FROM cashless_transactions r WHERE r.reverses_transaction_id = t.id)
            GROUP BY i.product_title
            ORDER BY total DESC, title ASC
            LIMIT :limit
        SQL, ['eventId' => $eventId, 'limit' => self::TOP_PRODUCTS_LIMIT]);

        return array_map(static fn (object $row) => new CashlessProductSummaryDTO(
            title: $row->title,
            quantity: (int) $row->quantity,
            total: (float) $row->total,
        ), $rows);
    }
}
