<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Application\Handlers\Admin\DTO\AdminDashboardResponseDTO;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAdminDashboardDataDTO;
use Illuminate\Support\Facades\DB;

class GetAdminDashboardDataHandler
{
    public function handle(GetAdminDashboardDataDTO $dto): AdminDashboardResponseDTO
    {
        $since = Carbon::now()->subDays($dto->days);
        $limit = $dto->limit;

        return new AdminDashboardResponseDTO(
            popular_events: $this->getPopularEvents($since, $limit),
            most_viewed_events: $this->getMostViewedEvents($since, $limit),
            top_organizers: $this->getTopOrganizers($since, $limit),
            recent_accounts: $this->getRecentAccounts($limit),
            recent_revenue: $this->getRecentRevenue($since),
            recent_orders_count: $this->getRecentOrdersCount($since),
            recent_orders_total: $this->getRecentOrdersTotal($since),
            recent_signups_count: $this->getRecentSignupsCount($since),
        );
    }

    private function getPopularEvents(Carbon $since, int $limit): array
    {
        $query = <<<SQL
            SELECT
                e.id,
                e.title,
                e.start_date,
                e.end_date,
                e.status,
                e.currency,
                o.name as organizer_name,
                a.name as account_name,
                es.products_sold,
                es.sales_total_gross,
                es.orders_created
            FROM events e
            JOIN event_statistics es ON es.event_id = e.id
            LEFT JOIN organizers o ON o.id = e.organizer_id
            LEFT JOIN accounts a ON a.id = e.account_id
            WHERE es.updated_at >= :since
              AND e.deleted_at IS NULL
              AND es.deleted_at IS NULL
              AND e.status IN (:statusLive, :statusDraft)
            ORDER BY es.products_sold DESC
            LIMIT :limit
        SQL;

        return DB::select($query, [
            'since' => $since,
            'statusLive' => EventStatus::LIVE->name,
            'statusDraft' => EventStatus::DRAFT->name,
            'limit' => $limit,
        ]);
    }

    private function getMostViewedEvents(Carbon $since, int $limit): array
    {
        $query = <<<SQL
            SELECT
                e.id,
                e.title,
                e.start_date,
                e.end_date,
                e.status,
                o.name as organizer_name,
                a.name as account_name,
                es.total_views
            FROM events e
            JOIN event_statistics es ON es.event_id = e.id
            LEFT JOIN organizers o ON o.id = e.organizer_id
            LEFT JOIN accounts a ON a.id = e.account_id
            WHERE es.updated_at >= :since
              AND e.deleted_at IS NULL
              AND es.deleted_at IS NULL
              AND e.status IN (:statusLive, :statusDraft)
              AND es.total_views > 0
            ORDER BY es.total_views DESC
            LIMIT :limit
        SQL;

        return DB::select($query, [
            'since' => $since,
            'statusLive' => EventStatus::LIVE->name,
            'statusDraft' => EventStatus::DRAFT->name,
            'limit' => $limit,
        ]);
    }

    private function getTopOrganizers(Carbon $since, int $limit): array
    {
        $query = <<<SQL
            SELECT
                o.id,
                o.name,
                a.name as account_name,
                COUNT(DISTINCT e.id) as events_count,
                COALESCE(SUM(es.products_sold), 0) as total_products_sold
            FROM organizers o
            LEFT JOIN accounts a ON a.id = o.account_id
            INNER JOIN events e ON e.organizer_id = o.id AND e.deleted_at IS NULL
            INNER JOIN event_statistics es ON es.event_id = e.id AND es.deleted_at IS NULL AND es.updated_at >= :since
            WHERE o.deleted_at IS NULL
            GROUP BY o.id, o.name, a.name
            HAVING COALESCE(SUM(es.products_sold), 0) > 0
            ORDER BY total_products_sold DESC
            LIMIT :limit
        SQL;

        return DB::select($query, [
            'since' => $since,
            'limit' => $limit,
        ]);
    }

    private function getRecentAccounts(int $limit): array
    {
        $query = <<<SQL
            SELECT
                a.id,
                a.name,
                a.email,
                a.created_at,
                a.stripe_connect_setup_complete,
                a.account_verified_at,
                COUNT(DISTINCT e.id) as events_count,
                COUNT(DISTINCT au.user_id) as users_count
            FROM accounts a
            LEFT JOIN events e ON e.account_id = a.id AND e.deleted_at IS NULL
            LEFT JOIN account_users au ON au.account_id = a.id AND au.deleted_at IS NULL
            WHERE a.deleted_at IS NULL
            GROUP BY a.id, a.name, a.email, a.created_at, a.stripe_connect_setup_complete, a.account_verified_at
            ORDER BY a.created_at DESC
            LIMIT :limit
        SQL;

        return DB::select($query, [
            'limit' => $limit,
        ]);
    }

    private function getRecentRevenue(Carbon $since): float
    {
        $query = <<<SQL
            SELECT COALESCE(SUM(es.sales_total_gross), 0) as total_revenue
            FROM event_statistics es
            WHERE es.updated_at >= :since
              AND es.deleted_at IS NULL
        SQL;

        $result = DB::selectOne($query, ['since' => $since]);

        return (float)($result->total_revenue ?? 0);
    }

    private function getRecentOrdersCount(Carbon $since): int
    {
        $query = <<<SQL
            SELECT COUNT(*) as count
            FROM orders o
            WHERE o.created_at >= :since
              AND o.deleted_at IS NULL
              AND o.status = :statusCompleted
              AND o.payment_status = :paymentStatusPaid
        SQL;

        $result = DB::selectOne($query, [
            'since' => $since,
            'statusCompleted' => OrderStatus::COMPLETED->name,
            'paymentStatusPaid' => OrderPaymentStatus::PAYMENT_RECEIVED->name,
        ]);

        return (int)($result->count ?? 0);
    }

    private function getRecentOrdersTotal(Carbon $since): float
    {
        $query = <<<SQL
            SELECT COALESCE(SUM(o.total_gross), 0) as total
            FROM orders o
            WHERE o.created_at >= :since
              AND o.deleted_at IS NULL
              AND o.status = :statusCompleted
              AND o.payment_status = :paymentStatusPaid
        SQL;

        $result = DB::selectOne($query, [
            'since' => $since,
            'statusCompleted' => OrderStatus::COMPLETED->name,
            'paymentStatusPaid' => OrderPaymentStatus::PAYMENT_RECEIVED->name,
        ]);

        return (float)($result->total ?? 0);
    }

    private function getRecentSignupsCount(Carbon $since): int
    {
        $query = <<<SQL
            SELECT COUNT(*) as count
            FROM accounts a
            WHERE a.created_at >= :since
              AND a.deleted_at IS NULL
        SQL;

        $result = DB::selectOne($query, ['since' => $since]);

        return (int)($result->count ?? 0);
    }
}
