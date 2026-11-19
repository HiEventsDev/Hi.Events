<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\DomainObjects\Enums\CrmActivityType;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmContactSource;
use HiEvents\DomainObjects\Enums\CrmProjectionOutcome;
use HiEvents\DomainObjects\Enums\CrmTicketRole;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\CrmSmsMarketingStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Crm\Repository\Eloquent\Concerns\LocksCrmOrganizer;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentStatusSql;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Models\CrmContact;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<CrmContactDomainObject>
 */
class CrmProjectionRepository extends BaseRepository implements CrmProjectionRepositoryInterface
{
    use LocksCrmOrganizer;

    private const VALID_EMAIL_PATTERN = '^[^@[:space:]]+@[^@[:space:]]+$';

    private const TRIMMED = ContactEmailNormalizer::SQL_TRIMMED_CHARACTERS;

    private const ORDER_EMAIL = 'lower(btrim(o.email, '.self::TRIMMED.'))';

    private const ATTENDEE_EMAIL = 'lower(btrim(a.email, '.self::TRIMMED.'))';

    private const ATTENDEE_OWNER_EMAIL = 'coalesce(nullif('.self::ATTENDEE_EMAIL.", ''), ".self::ORDER_EMAIL.')';

    private const WAITLIST_EMAIL = 'lower(btrim(w.email, '.self::TRIMMED.'))';

    private const ATTENDEE_IS_GUEST = '(nullif('.self::ATTENDEE_EMAIL.", '') IS NULL OR ".self::ATTENDEE_EMAIL.' = '.self::ORDER_EMAIL.')'
        ." AND lower(trim(concat_ws(' ', a.first_name, a.last_name))) <> lower(trim(concat_ws(' ', o.first_name, o.last_name)))";

    protected function getModel(): string
    {
        return CrmContact::class;
    }

    public function getDomainObject(): string
    {
        return CrmContactDomainObject::class;
    }

    public function findOrderScope(int $orderId): ?object
    {
        return $this->runQuery(fn () => $this->db->table('orders')
            ->join('events', 'events.id', '=', 'orders.event_id')
            ->where('orders.id', $orderId)
            ->select(['events.organizer_id', 'events.account_id'])
            ->first());
    }

    public function hasSyncSources(int $organizerId): bool
    {
        return $this->runQuery(fn () => $this->db->table('events')
            ->where('events.organizer_id', $organizerId)
            ->whereNull('events.deleted_at')
            ->where(static fn ($sources) => $sources
                ->whereExists(static fn ($orders) => $orders
                    ->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.event_id', 'events.id')
                    ->whereNull('orders.deleted_at'))
                ->orWhereExists(static fn ($entries) => $entries
                    ->selectRaw('1')
                    ->from('waitlist_entries')
                    ->whereColumn('waitlist_entries.event_id', 'events.id')
                    ->whereNull('waitlist_entries.deleted_at')))
            ->exists());
    }

    public function findEventIdsForOrganizer(int $organizerId, int $afterEventId, int $limit): array
    {
        return $this->runQuery(fn () => $this->db->table('events')
            ->where('organizer_id', $organizerId)
            ->whereNull('deleted_at')
            ->where('id', '>', $afterEventId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findOrderIdsForEvent(int $eventId, int $afterOrderId, int $limit): array
    {
        return $this->runQuery(fn () => $this->db->table('orders')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->where('id', '>', $afterOrderId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findWaitlistEntryIdsForEvent(int $eventId, int $afterEntryId, int $limit): array
    {
        return $this->runQuery(fn () => $this->db->table('waitlist_entries')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->where('id', '>', $afterEntryId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findProjectedOrderIdsNoLongerLive(int $organizerId, int $afterOrderId, int $limit): array
    {
        return $this->runQuery(fn () => $this->db->table('crm_contact_activities as a')
            ->leftJoin('orders as o', 'o.id', '=', 'a.order_id')
            ->leftJoin('events as e', 'e.id', '=', 'o.event_id')
            ->where('a.organizer_id', $organizerId)
            ->where('a.order_id', '>', $afterOrderId)
            ->where(static fn ($gone) => $gone
                ->whereNull('o.id')
                ->orWhereNotNull('o.deleted_at')
                ->orWhereNull('e.id')
                ->orWhereNotNull('e.deleted_at'))
            ->distinct()
            ->orderBy('a.order_id')
            ->limit($limit)
            ->pluck('a.order_id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findProjectedWaitlistEntryIdsNoLongerLive(int $organizerId, int $afterEntryId, int $limit): array
    {
        return $this->runQuery(fn () => $this->db->table('crm_contact_activities as a')
            ->leftJoin('waitlist_entries as w', 'w.id', '=', 'a.source_id')
            ->leftJoin('events as e', 'e.id', '=', 'w.event_id')
            ->where('a.organizer_id', $organizerId)
            ->where('a.source_type', CrmActivitySource::WAITLIST_ENTRY->value)
            ->where('a.source_id', '>', $afterEntryId)
            ->where(static fn ($gone) => $gone
                ->whereNull('w.id')
                ->orWhereNotNull('w.deleted_at')
                ->orWhereNull('e.id')
                ->orWhereNotNull('e.deleted_at'))
            ->distinct()
            ->orderBy('a.source_id')
            ->limit($limit)
            ->pluck('a.source_id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findOrderIdsForEmail(int $organizerId, string $email): array
    {
        $rows = $this->runQuery(fn () => $this->db->select(
            'SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id'
            .' WHERE e.organizer_id = :order_organizer AND o.deleted_at IS NULL'
            .' AND o.email ILIKE :order_pattern AND '.self::ORDER_EMAIL.' = :order_email'
            .' UNION SELECT a.order_id FROM attendees a JOIN events e ON e.id = a.event_id'
            .' WHERE e.organizer_id = :attendee_organizer AND a.deleted_at IS NULL'
            .' AND a.email ILIKE :attendee_pattern AND '.self::ATTENDEE_EMAIL.' = :attendee_email'
            .' ORDER BY 1',
            [
                'order_organizer' => $organizerId,
                'order_pattern' => $this->containsPattern($email),
                'order_email' => $email,
                'attendee_organizer' => $organizerId,
                'attendee_pattern' => $this->containsPattern($email),
                'attendee_email' => $email,
            ],
        ));

        return array_map(static fn (object $row) => (int) $row->id, $rows);
    }

    public function findWaitlistEntryIdsForEmail(int $organizerId, string $email): array
    {
        return $this->runQuery(fn () => $this->db->table('waitlist_entries as w')
            ->join('events as e', 'e.id', '=', 'w.event_id')
            ->where('e.organizer_id', $organizerId)
            ->whereNull('w.deleted_at')
            ->whereRaw(self::WAITLIST_EMAIL.' = ?', [$email])
            ->orderBy('w.id')
            ->pluck('w.id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findOrderIdsChangedSince(int $organizerId, CarbonInterface $since, CarbonInterface $until, int $afterOrderId, int $limit): array
    {
        $parts = [
            'updated' => 'SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id WHERE e.organizer_id = :updated_organizer'
                .' AND o.updated_at > :updated_since AND o.updated_at <= :updated_until AND o.id > :updated_after',
            'deleted' => 'SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id WHERE e.organizer_id = :deleted_organizer'
                .' AND o.deleted_at > :deleted_since AND o.deleted_at <= :deleted_until AND o.id > :deleted_after',
            'reserved' => 'SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id WHERE e.organizer_id = :reserved_organizer'
                .' AND o.status = :reserved_status AND o.reserved_until > :reserved_since AND o.reserved_until <= :reserved_until AND o.id > :reserved_after',
            'events' => 'SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id WHERE e.organizer_id = :events_organizer'
                .' AND e.deleted_at > :events_since AND e.deleted_at <= :events_until AND o.id > :events_after',
            'attendees' => 'SELECT a.order_id FROM attendees a JOIN events e ON e.id = a.event_id WHERE e.organizer_id = :attendees_organizer'
                .' AND a.updated_at > :attendees_since AND a.updated_at <= :attendees_until AND a.order_id > :attendees_after',
            'deleted_attendees' => 'SELECT a.order_id FROM attendees a JOIN events e ON e.id = a.event_id WHERE e.organizer_id = :deleted_attendees_organizer'
                .' AND a.deleted_at > :deleted_attendees_since AND a.deleted_at <= :deleted_attendees_until AND a.order_id > :deleted_attendees_after',
            'check_ins' => 'SELECT ci.order_id FROM attendee_check_ins ci JOIN events e ON e.id = ci.event_id WHERE e.organizer_id = :check_ins_organizer'
                .' AND ci.created_at > :check_ins_since AND ci.created_at <= :check_ins_until AND ci.order_id > :check_ins_after',
            'deleted_check_ins' => 'SELECT ci.order_id FROM attendee_check_ins ci JOIN events e ON e.id = ci.event_id WHERE e.organizer_id = :deleted_check_ins_organizer'
                .' AND ci.deleted_at > :deleted_check_ins_since AND ci.deleted_at <= :deleted_check_ins_until AND ci.order_id > :deleted_check_ins_after',
        ];

        $bindings = ['reserved_status' => OrderStatus::RESERVED->name, 'limit' => $limit];
        foreach (array_keys($parts) as $part) {
            $bindings[$part.'_organizer'] = $organizerId;
            $bindings[$part.'_since'] = $since->toDateTimeString();
            $bindings[$part.'_until'] = $until->toDateTimeString();
            $bindings[$part.'_after'] = $afterOrderId;
        }

        $rows = $this->runQuery(fn () => $this->db->select(
            implode(' UNION ', $parts).' ORDER BY 1 LIMIT :limit',
            $bindings,
        ));

        return array_map(static fn (object $row) => (int) $row->id, $rows);
    }

    public function findWaitlistEntryIdsChangedSince(int $organizerId, CarbonInterface $since, CarbonInterface $until, int $afterEntryId, int $limit): array
    {
        return $this->runQuery(fn () => $this->db->table('waitlist_entries')
            ->join('events', 'events.id', '=', 'waitlist_entries.event_id')
            ->where('events.organizer_id', $organizerId)
            ->where('waitlist_entries.id', '>', $afterEntryId)
            ->where(static function ($query) use ($since, $until) {
                $query->where(static fn ($inner) => $inner
                    ->where('waitlist_entries.updated_at', '>', $since)
                    ->where('waitlist_entries.updated_at', '<=', $until))
                    ->orWhere(static fn ($inner) => $inner
                        ->where('waitlist_entries.deleted_at', '>', $since)
                        ->where('waitlist_entries.deleted_at', '<=', $until))
                    ->orWhere(static fn ($inner) => $inner
                        ->where('events.deleted_at', '>', $since)
                        ->where('events.deleted_at', '<=', $until));
            })
            ->orderBy('waitlist_entries.id')
            ->limit($limit)
            ->pluck('waitlist_entries.id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function projectOrders(int $accountId, int $organizerId, array $orderIds, string $deletedContactSalt, CarbonInterface $now, bool $waitForLock = true): CrmProjectionOutcome
    {
        $orderIds = $this->toIdList($orderIds);

        if ($orderIds === '') {
            return CrmProjectionOutcome::UNCHANGED;
        }

        return $this->runQuery(fn () => $this->db->transaction(function () use ($accountId, $organizerId, $orderIds, $deletedContactSalt, $now, $waitForLock): CrmProjectionOutcome {
            if (! $this->acquireOrganizerLock($organizerId, $waitForLock)) {
                return CrmProjectionOutcome::LOCK_BUSY;
            }

            $digestBefore = $this->orderProjectionDigest($organizerId, $orderIds);

            $previouslyLinked = $this->db->table('crm_contact_activities')
                ->where('organizer_id', $organizerId)
                ->whereRaw("order_id IN ($orderIds)")
                ->distinct()
                ->pluck('contact_id')
                ->all();

            $cancelledAt = $this->db->table('crm_contact_activities')
                ->where('organizer_id', $organizerId)
                ->whereRaw("order_id IN ($orderIds)")
                ->where('type', CrmActivityType::ORDER_CANCELLED->value)
                ->groupBy('order_id')
                ->selectRaw('order_id, min(occurred_at) AS occurred_at')
                ->pluck('occurred_at', 'order_id')
                ->all();

            $this->db->delete(
                "DELETE FROM crm_contact_activities WHERE organizer_id = ? AND order_id IN ($orderIds) AND source_type IN (?, ?, ?)",
                [
                    $organizerId,
                    CrmActivitySource::ORDER->value,
                    CrmActivitySource::ATTENDEE->value,
                    CrmActivitySource::CHECK_IN->value,
                ],
            );

            $previouslyConsented = $this->db->table('crm_consent_events')
                ->where('organizer_id', $organizerId)
                ->where('source_type', CrmConsentSource::ORDER->value)
                ->whereRaw("source_id IN ($orderIds)")
                ->distinct()
                ->pluck('contact_id')
                ->all();

            $this->db->delete(
                "DELETE FROM crm_consent_events WHERE organizer_id = ? AND source_type = ? AND source_id IN ($orderIds)",
                [$organizerId, CrmConsentSource::ORDER->value],
            );

            $previouslySmsConsented = $this->db->table('crm_sms_consent_events')
                ->where('organizer_id', $organizerId)
                ->where('source_type', CrmConsentSource::ORDER->value)
                ->whereRaw("source_id IN ($orderIds)")
                ->distinct()
                ->pluck('contact_id')
                ->all();

            $this->db->delete(
                "DELETE FROM crm_sms_consent_events WHERE organizer_id = ? AND source_type = ? AND source_id IN ($orderIds)",
                [$organizerId, CrmConsentSource::ORDER->value],
            );

            $created = $this->createMissingContacts(
                accountId: $accountId,
                organizerId: $organizerId,
                deletedContactSalt: $deletedContactSalt,
                now: $now,
                sourceSql: '
                    SELECT '.self::ORDER_EMAIL." AS email, o.first_name, o.last_name, o.locale, '".CrmContactSource::ORDER->value."' AS source, 0 AS priority
                    FROM orders o
                    JOIN events e ON e.id = o.event_id AND e.organizer_id = :source_organizer AND e.deleted_at IS NULL
                    WHERE o.id IN ($orderIds) AND o.deleted_at IS NULL
                    UNION ALL
                    SELECT ".self::ATTENDEE_OWNER_EMAIL." AS email, a.first_name, a.last_name, a.locale, '".CrmContactSource::ATTENDEE->value."' AS source, 1 AS priority
                    FROM attendees a
                    JOIN orders o ON o.id = a.order_id AND o.deleted_at IS NULL
                    JOIN events e ON e.id = o.event_id AND e.organizer_id = :source_organizer_2 AND e.deleted_at IS NULL
                    WHERE a.order_id IN ($orderIds) AND a.deleted_at IS NULL AND a.status = '".AttendeeStatus::ACTIVE->name."'
                ",
                sourceBindings: ['source_organizer' => $organizerId, 'source_organizer_2' => $organizerId],
            );

            $this->insertOrderActivities($organizerId, $orderIds, $now, $cancelledAt);
            $this->insertTicketActivities($organizerId, $orderIds);
            $this->insertCheckInActivities($organizerId, $orderIds);
            $this->insertOrderConsent($organizerId, $orderIds);
            $this->insertOrderSmsConsent($organizerId, $orderIds);

            $nowLinked = $this->db->table('crm_contact_activities')
                ->where('organizer_id', $organizerId)
                ->whereRaw("order_id IN ($orderIds)")
                ->distinct()
                ->pluck('contact_id')
                ->all();

            $contactsChanged = $this->refreshContactsInTransaction(
                $organizerId,
                array_merge($previouslyLinked, $previouslyConsented, $previouslySmsConsented, $nowLinked, $created),
            );

            return $this->outcome($created, $digestBefore, $this->orderProjectionDigest($organizerId, $orderIds), $contactsChanged);
        }));
    }

    public function projectWaitlistEntries(int $accountId, int $organizerId, array $entryIds, string $deletedContactSalt, CarbonInterface $now, bool $waitForLock = true): CrmProjectionOutcome
    {
        $entryIds = $this->toIdList($entryIds);

        if ($entryIds === '') {
            return CrmProjectionOutcome::UNCHANGED;
        }

        return $this->runQuery(fn () => $this->db->transaction(function () use ($accountId, $organizerId, $entryIds, $deletedContactSalt, $now, $waitForLock): CrmProjectionOutcome {
            if (! $this->acquireOrganizerLock($organizerId, $waitForLock)) {
                return CrmProjectionOutcome::LOCK_BUSY;
            }

            $digestBefore = $this->waitlistProjectionDigest($organizerId, $entryIds);

            $previouslyLinked = $this->db->table('crm_contact_activities')
                ->where('organizer_id', $organizerId)
                ->where('source_type', CrmActivitySource::WAITLIST_ENTRY->value)
                ->whereRaw("source_id IN ($entryIds)")
                ->distinct()
                ->pluck('contact_id')
                ->all();

            $this->db->delete(
                "DELETE FROM crm_contact_activities WHERE organizer_id = ? AND source_type = ? AND source_id IN ($entryIds)",
                [$organizerId, CrmActivitySource::WAITLIST_ENTRY->value],
            );

            $created = $this->createMissingContacts(
                accountId: $accountId,
                organizerId: $organizerId,
                deletedContactSalt: $deletedContactSalt,
                now: $now,
                sourceSql: '
                    SELECT '.self::WAITLIST_EMAIL." AS email, w.first_name, w.last_name, w.locale, '".CrmContactSource::WAITLIST->value."' AS source, 0 AS priority
                    FROM waitlist_entries w
                    JOIN events e ON e.id = w.event_id AND e.organizer_id = :source_organizer AND e.deleted_at IS NULL
                    WHERE w.id IN ($entryIds) AND w.deleted_at IS NULL
                ",
                sourceBindings: ['source_organizer' => $organizerId],
            );

            $this->db->insert('
                INSERT INTO crm_contact_activities
                    (organizer_id, contact_id, type, source_type, source_id, event_id, event_occurrence_id, order_id, label, email, first_name, last_name, occurred_at)
                SELECT
                    :organizer, ce.contact_id, :type, :source_type, w.id, w.event_id, w.event_occurrence_id, NULL, w.status, ce.email, w.first_name, w.last_name, w.created_at
                FROM waitlist_entries w
                JOIN events e ON e.id = w.event_id AND e.organizer_id = :event_organizer AND e.deleted_at IS NULL
                JOIN crm_contact_emails ce ON ce.organizer_id = :email_organizer AND ce.email = '.self::WAITLIST_EMAIL."
                WHERE w.id IN ($entryIds) AND w.deleted_at IS NULL
                ON CONFLICT DO NOTHING
            ", [
                'organizer' => $organizerId,
                'event_organizer' => $organizerId,
                'email_organizer' => $organizerId,
                'type' => CrmActivityType::WAITLIST_JOINED->value,
                'source_type' => CrmActivitySource::WAITLIST_ENTRY->value,
            ]);

            $nowLinked = $this->db->table('crm_contact_activities')
                ->where('organizer_id', $organizerId)
                ->where('source_type', CrmActivitySource::WAITLIST_ENTRY->value)
                ->whereRaw("source_id IN ($entryIds)")
                ->distinct()
                ->pluck('contact_id')
                ->all();

            $contactsChanged = $this->refreshContactsInTransaction(
                $organizerId,
                array_merge($previouslyLinked, $nowLinked, $created),
            );

            return $this->outcome($created, $digestBefore, $this->waitlistProjectionDigest($organizerId, $entryIds), $contactsChanged);
        }));
    }

    public function refreshContacts(int $organizerId, array $contactIds): void
    {
        $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $contactIds): void {
            $this->lockOrganizer($organizerId);
            $this->refreshContactsInTransaction($organizerId, $contactIds);
        }));
    }

    /**
     * @return int[]
     */
    private function createMissingContacts(
        int $accountId,
        int $organizerId,
        string $deletedContactSalt,
        CarbonInterface $now,
        string $sourceSql,
        array $sourceBindings,
    ): array {
        $created = $this->db->select("
            WITH source AS ($sourceSql),
            candidates AS (
                SELECT DISTINCT ON (email) email, first_name, last_name, locale, source
                FROM source
                WHERE email IS NOT NULL AND email ~ :pattern AND char_length(email) <= 320
                ORDER BY email, priority
            ),
            created AS (
                INSERT INTO crm_contacts (account_id, organizer_id, email, first_name, last_name, locale, source, created_at, updated_at)
                SELECT :account, :organizer, c.email, nullif(trim(c.first_name), ''), nullif(trim(c.last_name), ''), c.locale, c.source, :created_at, :updated_at
                FROM candidates c
                WHERE NOT EXISTS (
                    SELECT 1 FROM crm_contact_emails ce WHERE ce.organizer_id = :email_organizer AND ce.email = c.email
                )
                AND NOT EXISTS (
                    SELECT 1 FROM crm_deleted_contacts d
                    WHERE d.organizer_id = :deleted_organizer
                      AND d.email_hash = ".DeletedContactHasher::hashSql(':salt', 'c.email').'
                )
                ON CONFLICT (organizer_id, email) DO NOTHING
                RETURNING id, organizer_id, email
            )
            INSERT INTO crm_contact_emails (organizer_id, contact_id, email, created_at, updated_at)
            SELECT organizer_id, id, email, :email_created_at, :email_updated_at FROM created
            ON CONFLICT (organizer_id, email) DO NOTHING
            RETURNING contact_id
        ', [
            ...$sourceBindings,
            'pattern' => self::VALID_EMAIL_PATTERN,
            'account' => $accountId,
            'organizer' => $organizerId,
            'email_organizer' => $organizerId,
            'deleted_organizer' => $organizerId,
            'salt' => $deletedContactSalt,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
            'email_created_at' => $now->toDateTimeString(),
            'email_updated_at' => $now->toDateTimeString(),
        ]);

        return array_map(static fn (object $row) => (int) $row->contact_id, $created);
    }

    /**
     * @param  array<int, string>  $cancelledAt
     */
    private function insertOrderActivities(int $organizerId, string $orderIds, CarbonInterface $now, array $cancelledAt): void
    {
        $this->db->insert('
            INSERT INTO crm_contact_activities
                (organizer_id, contact_id, type, source_type, source_id, event_id, order_id, promo_code_id, label, email, phone, phone_country, first_name, last_name, amount, currency, occurred_at)
            SELECT
                :organizer, ce.contact_id, t.type, :source_type, o.id, o.event_id, o.id, o.promo_code_id, o.promo_code, ce.email, o.phone, o.phone_country, o.first_name, o.last_name, t.amount, o.currency, t.occurred_at
            FROM orders o
            JOIN events e ON e.id = o.event_id AND e.organizer_id = :event_organizer AND e.deleted_at IS NULL
            JOIN crm_contact_emails ce ON ce.organizer_id = :email_organizer AND ce.email = '.self::ORDER_EMAIL."
            CROSS JOIN LATERAL (
                SELECT :completed_type AS type, o.total_gross AS amount, o.created_at AS occurred_at WHERE o.status = :completed
                UNION ALL
                SELECT :awaiting_type, o.total_gross, o.created_at WHERE o.status = :awaiting
                UNION ALL
                SELECT :cancelled_type, o.total_gross, coalesce(CAST(CAST(:cancelled_at AS jsonb)->>CAST(o.id AS text) AS timestamp), o.updated_at)
                WHERE o.status = :cancelled
                UNION ALL
                SELECT :refunded_type, o.total_refunded, coalesce((
                    SELECT max(r.created_at) FROM order_refunds r WHERE r.order_id = o.id AND r.deleted_at IS NULL
                ), o.updated_at)
                WHERE o.status = :refund_completed AND o.total_refunded > 0
                UNION ALL
                SELECT :abandoned_type, o.total_gross, o.created_at
                WHERE o.status = :abandoned OR (o.status = :reserved AND o.reserved_until < :now)
            ) t
            WHERE o.id IN ($orderIds) AND o.deleted_at IS NULL
            ON CONFLICT DO NOTHING
        ", [
            'organizer' => $organizerId,
            'event_organizer' => $organizerId,
            'email_organizer' => $organizerId,
            'source_type' => CrmActivitySource::ORDER->value,
            'completed_type' => CrmActivityType::ORDER_COMPLETED->value,
            'completed' => OrderStatus::COMPLETED->name,
            'awaiting_type' => CrmActivityType::ORDER_AWAITING_PAYMENT->value,
            'awaiting' => OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            'cancelled_type' => CrmActivityType::ORDER_CANCELLED->value,
            'cancelled' => OrderStatus::CANCELLED->name,
            'cancelled_at' => json_encode((object) $cancelledAt),
            'refunded_type' => CrmActivityType::ORDER_REFUNDED->value,
            'refund_completed' => OrderStatus::COMPLETED->name,
            'abandoned_type' => CrmActivityType::CHECKOUT_ABANDONED->value,
            'abandoned' => OrderStatus::ABANDONED->name,
            'reserved' => OrderStatus::RESERVED->name,
            'now' => $now->toDateTimeString(),
        ]);
    }

    private function insertTicketActivities(int $organizerId, string $orderIds): void
    {
        $this->db->insert('
            INSERT INTO crm_contact_activities
                (organizer_id, contact_id, type, role, source_type, source_id, event_id, event_occurrence_id, order_id, product_id, email, first_name, last_name, occurred_at)
            SELECT
                :organizer, ce.contact_id, :type,
                CASE WHEN '.self::ATTENDEE_IS_GUEST.' THEN :guest ELSE :self END,
                :source_type, a.id, a.event_id, a.event_occurrence_id, a.order_id, a.product_id, ce.email, a.first_name, a.last_name, a.created_at
            FROM attendees a
            JOIN orders o ON o.id = a.order_id AND o.deleted_at IS NULL
            JOIN events e ON e.id = o.event_id AND e.organizer_id = :event_organizer AND e.deleted_at IS NULL
            JOIN crm_contact_emails ce ON ce.organizer_id = :email_organizer AND ce.email = '.self::ATTENDEE_OWNER_EMAIL."
            WHERE a.order_id IN ($orderIds) AND a.deleted_at IS NULL AND a.status = :active
            ON CONFLICT DO NOTHING
        ", [
            'organizer' => $organizerId,
            'event_organizer' => $organizerId,
            'email_organizer' => $organizerId,
            'type' => CrmActivityType::TICKET_HELD->value,
            'guest' => CrmTicketRole::GUEST->value,
            'self' => CrmTicketRole::SELF->value,
            'source_type' => CrmActivitySource::ATTENDEE->value,
            'active' => AttendeeStatus::ACTIVE->name,
        ]);
    }

    private function insertCheckInActivities(int $organizerId, string $orderIds): void
    {
        $this->db->insert('
            INSERT INTO crm_contact_activities
                (organizer_id, contact_id, type, role, source_type, source_id, event_id, event_occurrence_id, order_id, product_id, email, first_name, last_name, occurred_at)
            SELECT DISTINCT ON (a.id, coalesce(ci.event_occurrence_id, a.event_occurrence_id))
                :organizer, ce.contact_id, :type,
                CASE WHEN '.self::ATTENDEE_IS_GUEST.' THEN :guest ELSE :self END,
                :source_type, ci.id, a.event_id, coalesce(ci.event_occurrence_id, a.event_occurrence_id), a.order_id, a.product_id, ce.email, a.first_name, a.last_name, ci.created_at
            FROM attendee_check_ins ci
            JOIN attendees a ON a.id = ci.attendee_id AND a.deleted_at IS NULL AND a.status = :active
            JOIN orders o ON o.id = a.order_id AND o.deleted_at IS NULL
            JOIN events e ON e.id = o.event_id AND e.organizer_id = :event_organizer AND e.deleted_at IS NULL
            JOIN crm_contact_emails ce ON ce.organizer_id = :email_organizer AND ce.email = '.self::ATTENDEE_OWNER_EMAIL."
            WHERE a.order_id IN ($orderIds) AND ci.deleted_at IS NULL
            ORDER BY a.id, coalesce(ci.event_occurrence_id, a.event_occurrence_id), ci.created_at, ci.id
            ON CONFLICT DO NOTHING
        ", [
            'organizer' => $organizerId,
            'event_organizer' => $organizerId,
            'email_organizer' => $organizerId,
            'type' => CrmActivityType::CHECKED_IN->value,
            'guest' => CrmTicketRole::GUEST->value,
            'self' => CrmTicketRole::SELF->value,
            'source_type' => CrmActivitySource::CHECK_IN->value,
            'active' => AttendeeStatus::ACTIVE->name,
        ]);
    }

    private function insertOrderConsent(int $organizerId, string $orderIds): void
    {
        $this->db->insert('
            INSERT INTO crm_consent_events (organizer_id, contact_id, email, action, source_type, source_id, occurred_at)
            SELECT :organizer, ce.contact_id, ce.email, :action, :source_type, o.id, o.opted_into_marketing_at
            FROM orders o
            JOIN events e ON e.id = o.event_id AND e.organizer_id = :event_organizer AND e.deleted_at IS NULL
            JOIN crm_contact_emails ce ON ce.organizer_id = :email_organizer AND ce.email = '.self::ORDER_EMAIL."
            WHERE o.id IN ($orderIds)
              AND o.deleted_at IS NULL
              AND o.opted_into_marketing_at IS NOT NULL
              AND o.status = :completed
            ON CONFLICT DO NOTHING
        ", [
            'organizer' => $organizerId,
            'event_organizer' => $organizerId,
            'email_organizer' => $organizerId,
            'action' => CrmConsentAction::OPT_IN->value,
            'source_type' => CrmConsentSource::ORDER->value,
            'completed' => OrderStatus::COMPLETED->name,
        ]);
    }

    private function insertOrderSmsConsent(int $organizerId, string $orderIds): void
    {
        $this->db->insert('
            INSERT INTO crm_sms_consent_events (organizer_id, contact_id, email, phone, action, source_type, source_id, occurred_at)
            SELECT :organizer, ce.contact_id, ce.email, o.phone, :action, :source_type, o.id, o.sms_opted_in_at
            FROM orders o
            JOIN events e ON e.id = o.event_id AND e.organizer_id = :event_organizer AND e.deleted_at IS NULL
            JOIN crm_contact_emails ce ON ce.organizer_id = :email_organizer AND ce.email = '.self::ORDER_EMAIL."
            WHERE o.id IN ($orderIds)
              AND o.deleted_at IS NULL
              AND o.sms_opted_in_at IS NOT NULL
              AND o.phone IS NOT NULL
              AND o.status = :completed
            ON CONFLICT DO NOTHING
        ", [
            'organizer' => $organizerId,
            'event_organizer' => $organizerId,
            'email_organizer' => $organizerId,
            'action' => CrmConsentAction::OPT_IN->value,
            'source_type' => CrmConsentSource::ORDER->value,
            'completed' => OrderStatus::COMPLETED->name,
        ]);
    }

    private function refreshContactsInTransaction(int $organizerId, array $contactIds): bool
    {
        $contactIds = $this->toIdList($contactIds);

        if ($contactIds === '') {
            return false;
        }

        $this->db->select(
            "SELECT id FROM crm_contacts WHERE organizer_id = ? AND id IN ($contactIds) ORDER BY id FOR UPDATE",
            [$organizerId],
        );

        $rollupsChanged = $this->refreshRollups($organizerId, $contactIds);
        $this->refreshNames($organizerId, $contactIds);
        $this->refreshSpend($organizerId, $contactIds);
        $statusesChanged = $this->refreshMarketingStatus($organizerId, $contactIds);
        $phonesChanged = $this->refreshPhones($organizerId, $contactIds);
        $smsStatusesChanged = $this->refreshSmsMarketingStatus($organizerId, $contactIds);
        $contactsDeleted = $this->deleteEmptyProjectedContacts($organizerId, $contactIds);

        return $rollupsChanged + $statusesChanged + $phonesChanged + $smsStatusesChanged + $contactsDeleted > 0;
    }

    private function refreshRollups(int $organizerId, string $contactIds): int
    {
        $order = CrmActivityType::ORDER_COMPLETED->value;
        $ticket = CrmActivityType::TICKET_HELD->value;
        $checkIn = CrmActivityType::CHECKED_IN->value;
        $guest = CrmTicketRole::GUEST->value;
        $notActivity = "('".CrmActivityType::CAMPAIGN_SENT->value."', '".CrmActivityType::IMPORTED->value."')";

        return $this->db->update("
            UPDATE crm_contacts c SET
                orders_count = s.orders_count,
                tickets_count = s.tickets_count,
                events_count = s.events_count,
                attended_count = s.attended_count,
                first_seen_at = least(s.first_seen_at, c.created_at),
                last_order_at = s.last_order_at,
                last_attended_at = s.last_attended_at,
                last_activity_at = s.last_activity_at
            FROM (
                SELECT
                    c2.id,
                    count(*) FILTER (WHERE a.type = '$order') AS orders_count,
                    count(*) FILTER (WHERE a.type = '$ticket') AS tickets_count,
                    count(DISTINCT a.event_id) FILTER (WHERE a.type IN ('$order', '$ticket')) AS events_count,
                    count(DISTINCT (a.event_id, coalesce(a.event_occurrence_id, 0))) FILTER (WHERE a.type = '$checkIn' AND a.role IS DISTINCT FROM '$guest') AS attended_count,
                    min(a.occurred_at) FILTER (WHERE a.type NOT IN $notActivity) AS first_seen_at,
                    max(a.occurred_at) FILTER (WHERE a.type = '$order') AS last_order_at,
                    max(a.occurred_at) FILTER (WHERE a.type = '$checkIn' AND a.role IS DISTINCT FROM '$guest') AS last_attended_at,
                    max(a.occurred_at) FILTER (WHERE a.type NOT IN $notActivity) AS last_activity_at
                FROM crm_contacts c2
                LEFT JOIN crm_contact_activities a ON a.contact_id = c2.id
                WHERE c2.organizer_id = ? AND c2.id IN ($contactIds)
                GROUP BY c2.id
            ) s
            WHERE c.id = s.id
              AND (c.orders_count, c.tickets_count, c.events_count, c.attended_count, c.first_seen_at, c.last_order_at, c.last_attended_at, c.last_activity_at)
                  IS DISTINCT FROM
                  (s.orders_count, s.tickets_count, s.events_count, s.attended_count, least(s.first_seen_at, c.created_at), s.last_order_at, s.last_attended_at, s.last_activity_at)
        ", [$organizerId]);
    }

    private function refreshNames(int $organizerId, string $contactIds): void
    {
        $self = CrmTicketRole::SELF->value;
        $orderSource = CrmActivitySource::ORDER->value;
        $attendeeSource = CrmActivitySource::ATTENDEE->value;

        $this->db->update("
            UPDATE crm_contacts c SET first_name = n.first_name, last_name = n.last_name
            FROM (
                SELECT DISTINCT ON (a.contact_id) a.contact_id, nullif(trim(a.first_name), '') AS first_name, nullif(trim(a.last_name), '') AS last_name
                FROM crm_contact_activities a
                WHERE a.contact_id IN ($contactIds)
                  AND nullif(trim(concat_ws(' ', a.first_name, a.last_name)), '') IS NOT NULL
                  AND (a.role IS NULL OR a.role = '$self')
                ORDER BY
                    a.contact_id,
                    CASE a.source_type WHEN '$orderSource' THEN 0 WHEN '$attendeeSource' THEN 1 ELSE 2 END,
                    a.occurred_at DESC,
                    a.id DESC
            ) n
            WHERE c.id = n.contact_id AND c.organizer_id = ? AND c.name_is_manual = false
        ", [$organizerId]);
    }

    private function refreshSpend(int $organizerId, string $contactIds): void
    {
        $order = CrmActivityType::ORDER_COMPLETED->value;
        $refund = CrmActivityType::ORDER_REFUNDED->value;

        $this->db->delete("DELETE FROM crm_contact_spend WHERE organizer_id = ? AND contact_id IN ($contactIds)", [$organizerId]);

        $this->db->insert("
            INSERT INTO crm_contact_spend (contact_id, organizer_id, currency, total_gross, total_refunded)
            SELECT
                a.contact_id, a.organizer_id, a.currency,
                coalesce(sum(a.amount) FILTER (WHERE a.type = '$order'), 0),
                coalesce(sum(a.amount) FILTER (WHERE a.type = '$refund'), 0)
            FROM crm_contact_activities a
            WHERE a.organizer_id = ?
              AND a.contact_id IN ($contactIds)
              AND a.currency IS NOT NULL
              AND a.type IN ('$order', '$refund')
            GROUP BY a.contact_id, a.organizer_id, a.currency
        ", [$organizerId]);
    }

    private function refreshMarketingStatus(int $organizerId, string $contactIds): int
    {
        $status = ConsentStatusSql::status('ev', 'ev.email = c2.email');
        $changedAt = ConsentStatusSql::changedAt('ev', 'ev.email = c2.email');

        return $this->db->update("
            UPDATE crm_contacts c SET
                email_marketing_status = s.status,
                email_marketing_status_changed_at = s.changed_at
            FROM (
                SELECT c2.id, $status AS status, $changedAt AS changed_at
                FROM crm_contacts c2
                LEFT JOIN crm_consent_events ev ON ev.contact_id = c2.id
                WHERE c2.organizer_id = ? AND c2.id IN ($contactIds)
                GROUP BY c2.id
            ) s
            WHERE c.id = s.id AND c.email_marketing_status IS DISTINCT FROM s.status
        ", [$organizerId]);
    }

    private function refreshPhones(int $organizerId, string $contactIds): int
    {
        return $this->db->update("
            UPDATE crm_contacts c SET phone = p.phone, phone_country = p.phone_country
            FROM (
                SELECT c2.id, latest.phone, latest.phone_country
                FROM crm_contacts c2
                LEFT JOIN LATERAL (
                    SELECT a.phone, a.phone_country
                    FROM crm_contact_activities a
                    WHERE a.contact_id = c2.id AND a.type IN (?, ?) AND a.phone IS NOT NULL
                    ORDER BY a.occurred_at DESC, a.id DESC
                    LIMIT 1
                ) latest ON TRUE
                WHERE c2.organizer_id = ? AND c2.id IN ($contactIds)
            ) p
            WHERE c.id = p.id AND (c.phone, c.phone_country) IS DISTINCT FROM (p.phone, p.phone_country)
        ", [CrmActivityType::ORDER_COMPLETED->value, CrmActivityType::ORDER_AWAITING_PAYMENT->value, $organizerId]);
    }

    private function refreshSmsMarketingStatus(int $organizerId, string $contactIds): int
    {
        $optIn = CrmConsentAction::OPT_IN->value;
        $optOut = CrmConsentAction::OPT_OUT->value;
        $subscribed = CrmSmsMarketingStatus::SUBSCRIBED->value;
        $unsubscribed = CrmSmsMarketingStatus::UNSUBSCRIBED->value;
        $notSubscribed = CrmSmsMarketingStatus::NOT_SUBSCRIBED->value;

        return $this->db->update("
            UPDATE crm_contacts c SET sms_marketing_status = s.status
            FROM (
                SELECT
                    c2.id,
                    coalesce(
                        (array_agg(CASE ev.action WHEN '$optIn' THEN '$subscribed' ELSE '$unsubscribed' END ORDER BY ev.occurred_at DESC, ev.id DESC)
                            FILTER (WHERE ev.id IS NOT NULL))[1],
                        '$notSubscribed'
                    ) AS status
                FROM crm_contacts c2
                LEFT JOIN crm_sms_consent_events ev
                    ON ev.organizer_id = c2.organizer_id
                    AND ev.phone = c2.phone
                    AND (ev.contact_id = c2.id OR ev.action = '$optOut')
                WHERE c2.organizer_id = ? AND c2.id IN ($contactIds)
                GROUP BY c2.id
            ) s
            WHERE c.id = s.id AND c.sms_marketing_status IS DISTINCT FROM s.status
        ", [$organizerId]);
    }

    private function deleteEmptyProjectedContacts(int $organizerId, string $contactIds): int
    {
        $projectedSources = "('".CrmContactSource::ORDER->value."', '".CrmContactSource::ATTENDEE->value."', '".CrmContactSource::WAITLIST->value."')";

        return $this->db->delete("
            DELETE FROM crm_contacts c
            WHERE c.organizer_id = ?
              AND c.id IN ($contactIds)
              AND c.source IN $projectedSources
              AND c.name_is_manual = false
              AND NOT EXISTS (SELECT 1 FROM crm_contact_activities a WHERE a.contact_id = c.id)
              AND NOT EXISTS (SELECT 1 FROM crm_contact_tags t WHERE t.contact_id = c.id)
              AND NOT EXISTS (SELECT 1 FROM crm_contact_notes n WHERE n.contact_id = c.id)
              AND NOT EXISTS (
                  SELECT 1 FROM crm_consent_events ev WHERE ev.contact_id = c.id AND ev.source_type <> '".CrmConsentSource::ORDER->value."'
              )
              AND NOT EXISTS (
                  SELECT 1 FROM crm_sms_consent_events sev WHERE sev.contact_id = c.id AND sev.source_type <> '".CrmConsentSource::ORDER->value."'
              )
        ", [$organizerId]);
    }

    /**
     * @param  int[]  $createdContactIds
     */
    private function outcome(array $createdContactIds, string $digestBefore, string $digestAfter, bool $contactsChanged): CrmProjectionOutcome
    {
        return $createdContactIds !== [] || $digestBefore !== $digestAfter || $contactsChanged
            ? CrmProjectionOutcome::CHANGED
            : CrmProjectionOutcome::UNCHANGED;
    }

    private function orderProjectionDigest(int $organizerId, string $orderIds): string
    {
        return $this->rowsDigest("
            SELECT CAST(ROW(a.contact_id, a.type, a.role, a.source_type, a.source_id, a.event_id, a.event_occurrence_id, a.order_id, a.product_id, a.promo_code_id, a.label, a.email, a.phone, a.phone_country, a.first_name, a.last_name, a.amount, a.currency, a.occurred_at) AS text) AS r
            FROM crm_contact_activities a
            WHERE a.organizer_id = ? AND a.order_id IN ($orderIds) AND a.source_type IN (?, ?, ?)
            UNION ALL
            SELECT CAST(ROW(ev.contact_id, ev.email, ev.action, ev.source_type, ev.source_id, ev.occurred_at) AS text)
            FROM crm_consent_events ev
            WHERE ev.organizer_id = ? AND ev.source_type = ? AND ev.source_id IN ($orderIds)
            UNION ALL
            SELECT CAST(ROW(sev.contact_id, sev.email, sev.phone, sev.action, sev.source_type, sev.source_id, sev.occurred_at) AS text)
            FROM crm_sms_consent_events sev
            WHERE sev.organizer_id = ? AND sev.source_type = ? AND sev.source_id IN ($orderIds)
        ", [
            $organizerId,
            CrmActivitySource::ORDER->value,
            CrmActivitySource::ATTENDEE->value,
            CrmActivitySource::CHECK_IN->value,
            $organizerId,
            CrmConsentSource::ORDER->value,
            $organizerId,
            CrmConsentSource::ORDER->value,
        ]);
    }

    private function waitlistProjectionDigest(int $organizerId, string $entryIds): string
    {
        return $this->rowsDigest("
            SELECT CAST(ROW(a.contact_id, a.type, a.source_type, a.source_id, a.event_id, a.event_occurrence_id, a.label, a.email, a.first_name, a.last_name, a.occurred_at) AS text) AS r
            FROM crm_contact_activities a
            WHERE a.organizer_id = ? AND a.source_type = ? AND a.source_id IN ($entryIds)
        ", [$organizerId, CrmActivitySource::WAITLIST_ENTRY->value]);
    }

    private function rowsDigest(string $rowsSql, array $bindings): string
    {
        return (string) $this->db->selectOne(
            "SELECT md5(coalesce(string_agg(r, '|' ORDER BY r), '')) AS digest FROM ($rowsSql) projected",
            $bindings,
        )->digest;
    }

    private function acquireOrganizerLock(int $organizerId, bool $waitForLock): bool
    {
        if (! $waitForLock) {
            return $this->tryLockOrganizer($organizerId);
        }

        $this->lockOrganizer($organizerId);

        return true;
    }

    private function containsPattern(string $value): string
    {
        return '%'.addcslashes($value, '\\%_').'%';
    }

    private function toIdList(array $ids): string
    {
        return implode(',', array_unique(array_map('intval', $ids)));
    }
}
