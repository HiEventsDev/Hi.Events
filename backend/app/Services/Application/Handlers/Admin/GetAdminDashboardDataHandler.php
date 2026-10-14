<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\AdminDashboardPeriod;
use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Application\Handlers\Admin\DTO\AdminDashboardResponseDTO;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAdminDashboardDataDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GetAdminDashboardDataHandler
{
    private const UPCOMING_WINDOW_DAYS = 7;

    public function handle(GetAdminDashboardDataDTO $dto): AdminDashboardResponseDTO
    {
        $now = CarbonImmutable::now();
        $since = $dto->period->since($now);
        $viewsSince = $since->utc()->startOfDay();

        return new AdminDashboardResponseDTO(
            period: $dto->period->value,
            attention: $this->getAttentionCounts(),
            commission_by_currency: $this->getCommissionByCurrency($now),
            views_since: $viewsSince->toDateString(),
            sales_by_currency: $this->getSalesByCurrency($since),
            orders_count: $this->getCompletedOrdersCount($since),
            tickets_sold: $this->getTicketsSold($since),
            signups_count: $this->countCreatedSince('accounts', $since),
            events_created_count: $this->countCreatedSince('events', $since),
            top_selling_events: $this->getTopSellingEvents($since, $dto->limit),
            top_accounts_by_commission: $this->getTopAccountsByCommission($since, $dto->limit),
            most_viewed_events: $this->getMostViewedEvents($viewsSince, $dto->limit),
            upcoming_events: $this->getUpcomingEvents($now, $dto->limit),
            recent_accounts: $this->getRecentAccounts($dto->limit),
        );
    }

    private function getAttentionCounts(): array
    {
        $result = DB::selectOne(<<<'SQL'
            SELECT
                (SELECT COUNT(*)
                 FROM event_spam_checks esc
                 JOIN events e ON e.id = esc.event_id AND e.deleted_at IS NULL AND e.status = :pendingReview
                 WHERE esc.status = :flagged AND esc.deleted_at IS NULL) AS flagged_events,
                (SELECT COUNT(*) FROM messages m WHERE m.status = :messagePendingReview AND m.deleted_at IS NULL) AS messages_pending_review,
                (SELECT COUNT(*) FROM failed_jobs) AS failed_jobs,
                (SELECT COUNT(*) FROM account_deletion_requests adr WHERE adr.status = :deletionRequested) AS pending_deletion_requests
        SQL, [
            'pendingReview' => EventStatus::PENDING_MANUAL_REVIEW->name,
            'flagged' => EventSpamCheckStatus::FLAGGED->name,
            'messagePendingReview' => MessageStatus::PENDING_REVIEW->name,
            'deletionRequested' => AccountDeletionRequestStatus::REQUESTED->name,
        ]);

        return [
            'flagged_events' => (int) $result->flagged_events,
            'messages_pending_review' => (int) $result->messages_pending_review,
            'failed_jobs' => (int) $result->failed_jobs,
            'pending_deletion_requests' => (int) $result->pending_deletion_requests,
        ];
    }

    private function getTopAccountsByCommission(CarbonImmutable $since, int $limit): array
    {
        $query = <<<'SQL'
            WITH totals AS (
                SELECT
                    a.id,
                    a.name,
                    a.email,
                    f.currency,
                    COUNT(*) AS orders_count,
                    SUM(f.application_fee_gross_amount) AS gross,
                    SUM(COALESCE(f.application_fee_net_amount, f.application_fee_gross_amount)) AS net
                FROM order_payment_platform_fees f
                JOIN orders o ON o.id = f.order_id
                JOIN events e ON e.id = o.event_id
                JOIN accounts a ON a.id = e.account_id
                WHERE f.deleted_at IS NULL
                  AND f.created_at >= :since
                GROUP BY a.id, a.name, a.email, f.currency
                HAVING SUM(f.application_fee_gross_amount) > 0
            ),
            ranked AS (
                SELECT totals.*, ROW_NUMBER() OVER (PARTITION BY currency ORDER BY gross DESC, id) AS currency_rank
                FROM totals
            )
            SELECT id, name, email, currency, orders_count, gross, net
            FROM ranked
            WHERE currency_rank <= :limit
            ORDER BY currency, gross DESC
        SQL;

        $rows = DB::select($query, ['since' => $since, 'limit' => $limit]);

        return array_map(static fn (object $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'currency' => $row->currency,
            'orders_count' => (int) $row->orders_count,
            'gross' => (float) $row->gross,
            'net' => (float) $row->net,
        ], $rows);
    }

    private function getCommissionByCurrency(CarbonImmutable $now): array
    {
        $query = <<<'SQL'
            SELECT
                f.currency,
                COALESCE(SUM(f.application_fee_gross_amount) FILTER (WHERE f.created_at >= :since24h), 0) AS gross_24h,
                COALESCE(SUM(COALESCE(f.application_fee_net_amount, f.application_fee_gross_amount)) FILTER (WHERE f.created_at >= :since24h), 0) AS net_24h,
                COALESCE(SUM(f.application_fee_gross_amount) FILTER (WHERE f.created_at >= :since7d), 0) AS gross_7d,
                COALESCE(SUM(COALESCE(f.application_fee_net_amount, f.application_fee_gross_amount)) FILTER (WHERE f.created_at >= :since7d), 0) AS net_7d,
                COALESCE(SUM(f.application_fee_gross_amount), 0) AS gross_30d,
                COALESCE(SUM(COALESCE(f.application_fee_net_amount, f.application_fee_gross_amount)), 0) AS net_30d
            FROM order_payment_platform_fees f
            WHERE f.deleted_at IS NULL
              AND f.created_at >= :since30d
            GROUP BY f.currency
            HAVING SUM(f.application_fee_gross_amount) > 0
            ORDER BY gross_30d DESC
        SQL;

        $rows = DB::select($query, [
            'since24h' => AdminDashboardPeriod::LAST_24_HOURS->since($now),
            'since7d' => AdminDashboardPeriod::LAST_7_DAYS->since($now),
            'since30d' => AdminDashboardPeriod::LAST_30_DAYS->since($now),
        ]);

        return array_map(static fn (object $row) => [
            'currency' => $row->currency,
            AdminDashboardPeriod::LAST_24_HOURS->value => ['gross' => (float) $row->gross_24h, 'net' => (float) $row->net_24h],
            AdminDashboardPeriod::LAST_7_DAYS->value => ['gross' => (float) $row->gross_7d, 'net' => (float) $row->net_7d],
            AdminDashboardPeriod::LAST_30_DAYS->value => ['gross' => (float) $row->gross_30d, 'net' => (float) $row->net_30d],
        ], $rows);
    }

    private function getSalesByCurrency(CarbonImmutable $since): array
    {
        $query = <<<'SQL'
            SELECT
                o.currency,
                COUNT(*) AS orders_count,
                COALESCE(SUM(o.total_gross - COALESCE(o.total_refunded, 0)), 0) AS total
            FROM orders o
            WHERE o.created_at >= :since
              AND o.deleted_at IS NULL
              AND o.status = :completed
              AND o.total_gross > 0
            GROUP BY o.currency
            ORDER BY total DESC
        SQL;

        $rows = DB::select($query, [
            'since' => $since,
            'completed' => OrderStatus::COMPLETED->name,
        ]);

        return array_map(static fn (object $row) => [
            'currency' => $row->currency,
            'orders_count' => (int) $row->orders_count,
            'total' => (float) $row->total,
        ], $rows);
    }

    private function getCompletedOrdersCount(CarbonImmutable $since): int
    {
        $result = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS count
            FROM orders o
            WHERE o.created_at >= :since
              AND o.deleted_at IS NULL
              AND o.status = :completed
        SQL, [
            'since' => $since,
            'completed' => OrderStatus::COMPLETED->name,
        ]);

        return (int) ($result->count ?? 0);
    }

    private function getTicketsSold(CarbonImmutable $since): int
    {
        $result = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS count
            FROM attendees a
            JOIN orders o ON o.id = a.order_id
            WHERE o.created_at >= :since
              AND o.deleted_at IS NULL
              AND o.status = :completed
              AND a.deleted_at IS NULL
              AND a.status = :active
        SQL, [
            'since' => $since,
            'completed' => OrderStatus::COMPLETED->name,
            'active' => AttendeeStatus::ACTIVE->name,
        ]);

        return (int) ($result->count ?? 0);
    }

    private function countCreatedSince(string $table, CarbonImmutable $since): int
    {
        return DB::table($table)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->count();
    }

    private function getTopSellingEvents(CarbonImmutable $since, int $limit): array
    {
        $query = <<<'SQL'
            WITH sales AS (
                SELECT o.event_id, COUNT(*) AS orders_count, COALESCE(SUM(o.total_gross - COALESCE(o.total_refunded, 0)), 0) AS sales_total
                FROM orders o
                WHERE o.created_at >= :since
                  AND o.deleted_at IS NULL
                  AND o.status = :completed
                GROUP BY o.event_id
            ),
            tickets AS (
                SELECT a.event_id, COUNT(*) AS tickets_sold
                FROM attendees a
                JOIN orders o ON o.id = a.order_id
                WHERE o.created_at >= :since
                  AND o.deleted_at IS NULL
                  AND o.status = :completed
                  AND a.deleted_at IS NULL
                  AND a.status = :active
                GROUP BY a.event_id
            )
            SELECT
                e.id,
                e.title,
                e.status,
                e.currency,
                e.account_id,
                a.name AS account_name,
                org.name AS organizer_name,
                COALESCE(t.tickets_sold, 0) AS tickets_sold,
                s.orders_count,
                s.sales_total
            FROM sales s
            JOIN events e ON e.id = s.event_id AND e.deleted_at IS NULL
            LEFT JOIN tickets t ON t.event_id = s.event_id
            LEFT JOIN organizers org ON org.id = e.organizer_id
            LEFT JOIN accounts a ON a.id = e.account_id
            ORDER BY tickets_sold DESC, s.orders_count DESC
            LIMIT :limit
        SQL;

        $rows = DB::select($query, [
            'since' => $since,
            'completed' => OrderStatus::COMPLETED->name,
            'active' => AttendeeStatus::ACTIVE->name,
            'limit' => $limit,
        ]);

        return array_map(static fn (object $row) => [
            'id' => $row->id,
            'title' => $row->title,
            'slug' => Str::slug($row->title),
            'status' => $row->status,
            'currency' => $row->currency,
            'account_id' => $row->account_id,
            'account_name' => $row->account_name,
            'organizer_name' => $row->organizer_name,
            'tickets_sold' => (int) $row->tickets_sold,
            'orders_count' => (int) $row->orders_count,
            'sales_total' => (float) $row->sales_total,
        ], $rows);
    }

    private function getMostViewedEvents(CarbonImmutable $since, int $limit): array
    {
        $query = <<<'SQL'
            WITH views AS (
                SELECT eds.event_id, SUM(eds.total_views) AS views
                FROM event_daily_statistics eds
                WHERE eds.date >= :sinceDate
                  AND eds.deleted_at IS NULL
                GROUP BY eds.event_id
                HAVING SUM(eds.total_views) > 0
            ),
            sales AS (
                SELECT o.event_id, COUNT(*) AS orders_count
                FROM orders o
                WHERE o.created_at >= :since
                  AND o.deleted_at IS NULL
                  AND o.status = :completed
                GROUP BY o.event_id
            )
            SELECT
                e.id,
                e.title,
                e.status,
                e.account_id,
                a.name AS account_name,
                org.name AS organizer_name,
                v.views,
                COALESCE(s.orders_count, 0) AS orders_count
            FROM views v
            JOIN events e ON e.id = v.event_id AND e.deleted_at IS NULL
            LEFT JOIN sales s ON s.event_id = v.event_id
            LEFT JOIN organizers org ON org.id = e.organizer_id
            LEFT JOIN accounts a ON a.id = e.account_id
            ORDER BY v.views DESC
            LIMIT :limit
        SQL;

        $rows = DB::select($query, [
            'sinceDate' => $since->toDateString(),
            'since' => $since->toDateTimeString(),
            'completed' => OrderStatus::COMPLETED->name,
            'limit' => $limit,
        ]);

        return array_map(static fn (object $row) => [
            'id' => $row->id,
            'title' => $row->title,
            'slug' => Str::slug($row->title),
            'status' => $row->status,
            'account_id' => $row->account_id,
            'account_name' => $row->account_name,
            'organizer_name' => $row->organizer_name,
            'views' => (int) $row->views,
            'orders_count' => (int) $row->orders_count,
        ], $rows);
    }

    private function getUpcomingEvents(CarbonImmutable $now, int $limit): array
    {
        $query = <<<'SQL'
            WITH recent_orders AS (
                SELECT o.event_id, COUNT(*) AS orders_count
                FROM orders o
                WHERE o.created_at >= :since24h
                  AND o.deleted_at IS NULL
                  AND o.status = :completed
                GROUP BY o.event_id
            )
            SELECT
                e.id,
                e.title,
                e.timezone,
                e.account_id,
                a.name AS account_name,
                org.name AS organizer_name,
                eo.id AS occurrence_id,
                eo.start_date,
                eo.capacity,
                COALESCE(eos.attendees_registered, 0) AS attendees_count,
                COALESCE(ro.orders_count, 0) AS orders_last_24h
            FROM event_occurrences eo
            JOIN events e ON e.id = eo.event_id AND e.deleted_at IS NULL AND e.status = :live
            LEFT JOIN event_occurrence_statistics eos ON eos.event_occurrence_id = eo.id AND eos.deleted_at IS NULL
            LEFT JOIN recent_orders ro ON ro.event_id = e.id
            LEFT JOIN organizers org ON org.id = e.organizer_id
            LEFT JOIN accounts a ON a.id = e.account_id
            WHERE eo.deleted_at IS NULL
              AND eo.status = :active
              AND eo.start_date >= :now
              AND eo.start_date <= :until
            ORDER BY attendees_count DESC, orders_last_24h DESC, eo.start_date ASC
            LIMIT :limit
        SQL;

        $rows = DB::select($query, [
            'since24h' => AdminDashboardPeriod::LAST_24_HOURS->since($now),
            'completed' => OrderStatus::COMPLETED->name,
            'live' => EventStatus::LIVE->name,
            'active' => EventOccurrenceStatus::ACTIVE->name,
            'now' => $now,
            'until' => $now->addDays(self::UPCOMING_WINDOW_DAYS),
            'limit' => $limit,
        ]);

        return array_map(static fn (object $row) => [
            'id' => $row->id,
            'title' => $row->title,
            'slug' => Str::slug($row->title),
            'timezone' => $row->timezone,
            'account_id' => $row->account_id,
            'account_name' => $row->account_name,
            'organizer_name' => $row->organizer_name,
            'occurrence_id' => $row->occurrence_id,
            'start_date' => $row->start_date,
            'capacity' => $row->capacity === null ? null : (int) $row->capacity,
            'attendees_count' => (int) $row->attendees_count,
            'orders_last_24h' => (int) $row->orders_last_24h,
        ], $rows);
    }

    private function getRecentAccounts(int $limit): array
    {
        $query = <<<'SQL'
            SELECT
                a.id,
                a.name,
                a.email,
                a.created_at,
                a.account_verified_at,
                EXISTS (
                    SELECT 1
                    FROM organizer_stripe_platforms osp
                    JOIN organizers o ON o.id = osp.organizer_id
                    WHERE o.account_id = a.id
                      AND osp.stripe_setup_completed_at IS NOT NULL
                      AND osp.deleted_at IS NULL
                      AND o.deleted_at IS NULL
                ) AS stripe_connect_setup_complete,
                (SELECT COUNT(*) FROM events e WHERE e.account_id = a.id AND e.deleted_at IS NULL) AS events_count
            FROM accounts a
            WHERE a.deleted_at IS NULL
            ORDER BY a.created_at DESC
            LIMIT :limit
        SQL;

        $rows = DB::select($query, ['limit' => $limit]);

        return array_map(static fn (object $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'created_at' => $row->created_at,
            'account_verified_at' => $row->account_verified_at,
            'stripe_connect_setup_complete' => (bool) $row->stripe_connect_setup_complete,
            'events_count' => (int) $row->events_count,
        ], $rows);
    }
}
