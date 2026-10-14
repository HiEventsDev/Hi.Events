<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\AccountLifecycleNudgeDomainObject;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\AccountLifecycleNudge;
use HiEvents\Repository\DTO\LifecycleEmail\AccountOwnerNudgeCandidateDTO;
use HiEvents\Repository\DTO\LifecycleEmail\EventNudgeCandidateDTO;
use HiEvents\Repository\DTO\LifecycleEmail\LifecycleNudgeSendCountsDTO;
use HiEvents\Repository\DTO\LifecycleEmail\StripeOnboardingNudgeCandidateDTO;
use HiEvents\Repository\Interfaces\AccountLifecycleNudgeRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<AccountLifecycleNudgeDomainObject>
 */
class AccountLifecycleNudgeRepository extends BaseRepository implements AccountLifecycleNudgeRepositoryInterface
{
    private const QUIET_PERIOD_DAYS = 3;

    protected function getModel(): string
    {
        return AccountLifecycleNudge::class;
    }

    public function getDomainObject(): string
    {
        return AccountLifecycleNudgeDomainObject::class;
    }

    public function findNoEventCandidates(
        CarbonInterface $createdAfter,
        CarbonInterface $createdBefore,
        int $limit,
    ): Collection {
        $rows = $this->db->select(<<<'SQL'
            SELECT DISTINCT ON (a.id)
                a.id AS account_id, u.id AS user_id, u.email, u.first_name
            FROM accounts a
            JOIN account_users au ON au.account_id = a.id AND au.is_account_owner = true
                                  AND au.status = 'ACTIVE' AND au.deleted_at IS NULL
            JOIN users u          ON u.id = au.user_id AND u.deleted_at IS NULL
                                  AND u.lifecycle_emails_unsubscribed_at IS NULL
            WHERE a.deleted_at IS NULL
              AND a.account_verified_at IS NOT NULL
              AND a.created_at >= ?
              AND a.created_at <= ?
              AND NOT EXISTS (SELECT 1 FROM events e WHERE e.account_id = a.id)
              AND NOT EXISTS (SELECT 1 FROM account_deletion_requests adr
                              WHERE adr.account_id = a.id AND adr.status = ?)
              AND NOT EXISTS (SELECT 1 FROM account_lifecycle_nudges n
                              WHERE n.account_id = a.id
                                AND (n.nudge_type = ? OR n.created_at > ?))
            ORDER BY a.id
            LIMIT ?
        SQL, [
            $createdAfter->toDateTimeString(),
            $createdBefore->toDateTimeString(),
            AccountDeletionRequestStatus::REQUESTED->name,
            LifecycleNudgeType::NO_EVENT_CREATED->name,
            $this->quietPeriodStart(),
            $limit,
        ]);

        return collect($rows)->map(static fn (object $row) => new AccountOwnerNudgeCandidateDTO(
            accountId: (int) $row->account_id,
            userId: (int) $row->user_id,
            email: $row->email,
            firstName: $row->first_name,
        ));
    }

    public function findStripeOnboardingCandidates(
        CarbonInterface $triggeredAfter,
        CarbonInterface $triggeredBefore,
        array $excludedTimezones,
        int $limit,
    ): Collection {
        $rows = $this->db->select(<<<'SQL'
            WITH first_paid_product AS (
                SELECT e.organizer_id, MIN(p.created_at) AS created_at
                FROM products p
                JOIN events e               ON e.id = p.event_id AND e.deleted_at IS NULL
                JOIN product_prices pp      ON pp.product_id = p.id AND pp.deleted_at IS NULL AND pp.price > 0
                LEFT JOIN event_settings es ON es.event_id = e.id
                WHERE p.deleted_at IS NULL
                  AND e.organizer_id IS NOT NULL
                  AND (es.payment_providers IS NULL OR es.payment_providers::jsonb @> ?::jsonb)
                GROUP BY e.organizer_id
            ),
            stripe_started AS (
                SELECT organizer_id, MIN(created_at) AS created_at
                FROM organizer_stripe_platforms
                WHERE deleted_at IS NULL AND stripe_account_id IS NOT NULL
                GROUP BY organizer_id
            )
            SELECT DISTINCT ON (a.id)
                a.id AS account_id, o.id AS organizer_id, o.name AS organizer_name,
                u.id AS user_id, u.email, u.first_name
            FROM first_paid_product fpp
            JOIN organizers o            ON o.id = fpp.organizer_id AND o.deleted_at IS NULL
            JOIN accounts a              ON a.id = o.account_id AND a.deleted_at IS NULL
                                         AND a.account_verified_at IS NOT NULL
            JOIN account_users au        ON au.account_id = a.id AND au.is_account_owner = true
                                         AND au.status = 'ACTIVE' AND au.deleted_at IS NULL
            JOIN users u                 ON u.id = au.user_id AND u.deleted_at IS NULL
                                         AND u.lifecycle_emails_unsubscribed_at IS NULL
            LEFT JOIN stripe_started ss  ON ss.organizer_id = o.id
            WHERE GREATEST(ss.created_at, fpp.created_at) >= ?
              AND GREATEST(ss.created_at, fpp.created_at) <= ?
              AND (a.timezone IS NULL OR a.timezone NOT IN (SELECT jsonb_array_elements_text(?::jsonb)))
              AND NOT EXISTS (SELECT 1 FROM organizer_stripe_platforms c
                              WHERE c.organizer_id = o.id AND c.deleted_at IS NULL
                                AND c.stripe_setup_completed_at IS NOT NULL)
              AND NOT EXISTS (SELECT 1 FROM account_deletion_requests adr
                              WHERE adr.account_id = a.id AND adr.status = ?)
              AND NOT EXISTS (SELECT 1 FROM account_lifecycle_nudges n
                              WHERE n.account_id = a.id
                                AND (n.nudge_type = ? OR n.created_at > ?))
            ORDER BY a.id, o.id
            LIMIT ?
        SQL, [
            json_encode([PaymentProviders::STRIPE->name]),
            $triggeredAfter->toDateTimeString(),
            $triggeredBefore->toDateTimeString(),
            json_encode(array_values($excludedTimezones)),
            AccountDeletionRequestStatus::REQUESTED->name,
            LifecycleNudgeType::STRIPE_ONBOARDING_INCOMPLETE->name,
            $this->quietPeriodStart(),
            $limit,
        ]);

        return collect($rows)->map(static fn (object $row) => new StripeOnboardingNudgeCandidateDTO(
            accountId: (int) $row->account_id,
            userId: (int) $row->user_id,
            organizerId: (int) $row->organizer_id,
            organizerName: $row->organizer_name,
            email: $row->email,
            firstName: $row->first_name,
        ));
    }

    public function findDraftEventCandidates(
        CarbonInterface $createdAfter,
        CarbonInterface $createdBefore,
        int $limit,
    ): Collection {
        $rows = $this->db->select(<<<'SQL'
            SELECT DISTINCT ON (a.id)
                a.id AS account_id, e.id AS event_id, e.title,
                u.id AS user_id, u.email, u.first_name
            FROM events e
            JOIN accounts a       ON a.id = e.account_id AND a.deleted_at IS NULL
                                  AND a.account_verified_at IS NOT NULL
            JOIN account_users au ON au.account_id = a.id AND au.is_account_owner = true
                                  AND au.status = 'ACTIVE' AND au.deleted_at IS NULL
            JOIN users u          ON u.id = au.user_id AND u.deleted_at IS NULL
                                  AND u.lifecycle_emails_unsubscribed_at IS NULL
            WHERE e.deleted_at IS NULL
              AND e.status = ?
              AND e.created_at >= ?
              AND e.created_at <= ?
              AND NOT EXISTS (SELECT 1 FROM events l WHERE l.account_id = a.id
                                AND l.deleted_at IS NULL AND l.status = ?)
              AND NOT EXISTS (SELECT 1 FROM account_deletion_requests adr
                              WHERE adr.account_id = a.id AND adr.status = ?)
              AND NOT EXISTS (SELECT 1 FROM account_lifecycle_nudges n
                              WHERE n.account_id = a.id
                                AND (n.nudge_type = ? OR n.created_at > ?))
            ORDER BY a.id, e.created_at
            LIMIT ?
        SQL, [
            EventStatus::DRAFT->name,
            $createdAfter->toDateTimeString(),
            $createdBefore->toDateTimeString(),
            EventStatus::LIVE->name,
            AccountDeletionRequestStatus::REQUESTED->name,
            LifecycleNudgeType::EVENT_DRAFT_STALE->name,
            $this->quietPeriodStart(),
            $limit,
        ]);

        return collect($rows)->map(static fn (object $row) => new EventNudgeCandidateDTO(
            accountId: (int) $row->account_id,
            userId: (int) $row->user_id,
            eventId: (int) $row->event_id,
            eventTitle: $row->title,
            email: $row->email,
            firstName: $row->first_name,
        ));
    }

    public function findLiveEventWithoutSalesCandidates(
        CarbonInterface $readyAfter,
        CarbonInterface $readyBefore,
        CarbonInterface $upcomingAfter,
        int $limit,
    ): Collection {
        $rows = $this->db->select(<<<'SQL'
            SELECT DISTINCT ON (a.id)
                a.id AS account_id, e.id AS event_id, e.title,
                u.id AS user_id, u.email, u.first_name
            FROM events e
            JOIN accounts a       ON a.id = e.account_id AND a.deleted_at IS NULL
                                  AND a.account_verified_at IS NOT NULL
            JOIN account_users au ON au.account_id = a.id AND au.is_account_owner = true
                                  AND au.status = 'ACTIVE' AND au.deleted_at IS NULL
            JOIN users u          ON u.id = au.user_id AND u.deleted_at IS NULL
                                  AND u.lifecycle_emails_unsubscribed_at IS NULL
            JOIN LATERAL (
                SELECT MIN(osp.stripe_setup_completed_at) AS completed_at
                FROM organizer_stripe_platforms osp
                WHERE osp.organizer_id = e.organizer_id AND osp.deleted_at IS NULL
            ) stripe ON stripe.completed_at IS NOT NULL
            WHERE e.deleted_at IS NULL
              AND e.status = ?
              AND e.created_at >= ?
              AND GREATEST(e.updated_at, stripe.completed_at) >= ?
              AND GREATEST(e.updated_at, stripe.completed_at) <= ?
              AND EXISTS (SELECT 1 FROM products p
                          JOIN product_prices pp ON pp.product_id = p.id AND pp.deleted_at IS NULL
                          WHERE p.event_id = e.id AND p.deleted_at IS NULL AND pp.price > 0)
              AND EXISTS (SELECT 1 FROM event_occurrences eo
                          WHERE eo.event_id = e.id AND eo.deleted_at IS NULL
                            AND eo.status = ? AND eo.start_date > ?)
              AND NOT EXISTS (SELECT 1 FROM orders o
                              JOIN events oe ON oe.id = o.event_id
                              WHERE oe.account_id = a.id AND o.status IN (?, ?))
              AND NOT EXISTS (SELECT 1 FROM account_deletion_requests adr
                              WHERE adr.account_id = a.id AND adr.status = ?)
              AND NOT EXISTS (SELECT 1 FROM account_lifecycle_nudges n
                              WHERE n.account_id = a.id
                                AND (n.nudge_type = ? OR n.created_at > ?))
            ORDER BY a.id, e.created_at
            LIMIT ?
        SQL, [
            EventStatus::LIVE->name,
            $readyAfter->toDateTimeString(),
            $readyAfter->toDateTimeString(),
            $readyBefore->toDateTimeString(),
            EventOccurrenceStatus::ACTIVE->name,
            $upcomingAfter->toDateTimeString(),
            OrderStatus::COMPLETED->name,
            OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            AccountDeletionRequestStatus::REQUESTED->name,
            LifecycleNudgeType::LIVE_EVENT_NO_SALES->name,
            $this->quietPeriodStart(),
            $limit,
        ]);

        return collect($rows)->map(static fn (object $row) => new EventNudgeCandidateDTO(
            accountId: (int) $row->account_id,
            userId: (int) $row->user_id,
            eventId: (int) $row->event_id,
            eventTitle: $row->title,
            email: $row->email,
            firstName: $row->first_name,
        ));
    }

    public function claim(int $accountId, LifecycleNudgeType $nudgeType, ?int $eventId = null): bool
    {
        return $this->db->table('account_lifecycle_nudges')->insertOrIgnore([
            'account_id' => $accountId,
            'nudge_type' => $nudgeType->name,
            'event_id' => $eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    public function release(int $accountId, LifecycleNudgeType $nudgeType): void
    {
        $this->deleteWhere([
            'account_id' => $accountId,
            'nudge_type' => $nudgeType->name,
        ]);
    }

    public function recordProviderMessageId(int $accountId, LifecycleNudgeType $nudgeType, string $providerMessageId): void
    {
        $this->updateWhere(
            ['provider_message_id' => $providerMessageId],
            ['account_id' => $accountId, 'nudge_type' => $nudgeType->name],
        );
    }

    public function countsByType(CarbonInterface $recentSince): array
    {
        return $this->runQuery(fn () => $this->db->table('account_lifecycle_nudges as n')
            ->leftJoin('email_deliveries as d', 'd.provider_message_id', '=', 'n.provider_message_id')
            ->groupBy('n.nudge_type')
            ->select('n.nudge_type')
            ->selectRaw('COUNT(DISTINCT n.id) AS total')
            ->selectRaw('COUNT(DISTINCT n.id) FILTER (WHERE n.created_at >= ?) AS recent', [$recentSince->toDateTimeString()])
            ->selectRaw('COUNT(DISTINCT n.id) FILTER (WHERE d.delivered_at IS NOT NULL) AS delivered')
            ->selectRaw('COUNT(DISTINCT n.id) FILTER (WHERE d.bounced_at IS NOT NULL) AS bounced')
            ->selectRaw('COUNT(DISTINCT n.id) FILTER (WHERE d.first_opened_at IS NOT NULL) AS opened')
            ->selectRaw('COUNT(DISTINCT n.id) FILTER (WHERE d.first_clicked_at IS NOT NULL) AS clicked')
            ->get()
            ->mapWithKeys(static fn ($row) => [
                $row->nudge_type => new LifecycleNudgeSendCountsDTO(
                    total: (int) $row->total,
                    lastThirtyDays: (int) $row->recent,
                    delivered: (int) $row->delivered,
                    bounced: (int) $row->bounced,
                    opened: (int) $row->opened,
                    clicked: (int) $row->clicked,
                ),
            ])
            ->all());
    }

    private function quietPeriodStart(): string
    {
        return Carbon::now()->subDays(self::QUIET_PERIOD_DAYS)->toDateTimeString();
    }
}
