<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use Carbon\CarbonInterface;
use Closure;
use HiEvents\DomainObjects\CrmAutomationDomainObject;
use HiEvents\DomainObjects\Status\CrmCampaignRecipientStatus;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmAutomationRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\CompiledSegmentDTO;
use HiEvents\Models\CrmAutomation;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmAutomationDomainObject>
 */
class CrmAutomationRepository extends BaseRepository implements CrmAutomationRepositoryInterface
{
    private const MAX_EVENTS_PER_RUN = 20;

    private const MAX_ABANDONED_CONTACTS_PER_RUN = 5000;

    private const ORDER_EMAIL = 'lower(btrim(o.email, '.ContactEmailNormalizer::SQL_TRIMMED_CHARACTERS.'))';

    private const LATER_ORDER_EMAIL = 'lower(btrim(later.email, '.ContactEmailNormalizer::SQL_TRIMMED_CHARACTERS.'))';

    protected function getModel(): string
    {
        return CrmAutomation::class;
    }

    public function getDomainObject(): string
    {
        return CrmAutomationDomainObject::class;
    }

    public function findByOrganizerIdWithStats(int $organizerId): Collection
    {
        return $this->runQuery(function () use ($organizerId) {
            $stats = $this->db->table('crm_campaigns AS c')
                ->join('crm_campaign_recipients AS r', 'r.campaign_id', '=', 'c.id')
                ->where('c.organizer_id', $organizerId)
                ->whereNotNull('c.automation_id')
                ->where('r.status', CrmCampaignRecipientStatus::SENT->value)
                ->groupBy('c.automation_id')
                ->selectRaw('c.automation_id, count(*) AS sent_count, max(r.sent_at) AS last_sent_at')
                ->get()
                ->keyBy('automation_id');

            return $this->handleResults($this->model->where('organizer_id', $organizerId)->orderBy('id')->get())
                ->each(static fn (CrmAutomationDomainObject $automation) => $automation
                    ->setSentCount((int) ($stats->get($automation->getId())->sent_count ?? 0))
                    ->setLastSentAt($stats->get($automation->getId())->last_sent_at ?? null));
        });
    }

    public function findEnabledAccountIdsByIdAfter(int $afterId, int $limit): Collection
    {
        return $this->runQuery(fn () => $this->db->table('crm_automations')
            ->where('crm_automations.id', '>', $afterId)
            ->where('crm_automations.is_enabled', true)
            ->whereNull('crm_automations.deleted_at')
            ->whereExists(static fn ($organizers) => $organizers
                ->selectRaw('1')
                ->from('organizers')
                ->whereColumn('organizers.id', 'crm_automations.organizer_id')
                ->whereNull('organizers.deleted_at'))
            ->orderBy('crm_automations.id')
            ->limit($limit)
            ->pluck('crm_automations.account_id', 'crm_automations.id')
            ->map(static fn ($accountId) => (int) $accountId));
    }

    public function findLiveUpcomingEventIds(int $organizerId, CarbonInterface $now): array
    {
        return $this->runQuery(fn () => $this->liveUpcomingEvents($organizerId, $now)
            ->pluck('events.id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findPublishedEventIds(int $organizerId): array
    {
        return $this->runQuery(fn () => $this->db->table('events')
            ->where('organizer_id', $organizerId)
            ->where('status', '<>', EventStatus::DRAFT->name)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findNewlyLiveEventIds(CrmAutomationDomainObject $automation, CarbonInterface $settledBefore, bool $mustHavePassedSpamCheck): array
    {
        return $this->runQuery(fn () => $this->unannouncedEvents($automation, $settledBefore)
            ->when($mustHavePassedSpamCheck, fn ($events) => $events->whereExists($this->passedSpamCheck()))
            ->orderBy('events.id')
            ->limit(self::MAX_EVENTS_PER_RUN)
            ->pluck('events.id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function findEventAwaitingSpamCheck(CrmAutomationDomainObject $automation, CarbonInterface $settledBefore): ?object
    {
        return $this->runQuery(fn () => $this->unannouncedEvents($automation, $settledBefore)
            ->whereNotExists($this->passedSpamCheck())
            ->orderBy('events.id')
            ->selectRaw('events.id, events.title, EXISTS (SELECT 1 FROM event_spam_checks WHERE event_spam_checks.event_id = events.id) AS has_spam_check')
            ->first());
    }

    private function unannouncedEvents(CrmAutomationDomainObject $automation, CarbonInterface $settledBefore): Builder
    {
        return $this->liveUpcomingEvents($automation->getOrganizerId(), $settledBefore)
            ->where('events.updated_at', '<=', $settledBefore)
            ->whereNotIn('events.id', $automation->getBaselineEventIds() ?? [])
            ->whereNotExists(fn ($campaigns) => $campaigns
                ->selectRaw('1')
                ->from('crm_campaigns')
                ->where('crm_campaigns.automation_id', $automation->getId())
                ->whereRaw("crm_campaigns.automation_run_key = 'event:' || events.id")
                ->where(fn ($claimed) => $claimed
                    ->where('crm_campaigns.status', '<>', CrmCampaignStatus::DRAFT->value)
                    ->orWhere('crm_campaigns.created_at', '>=', $this->orphanedBefore())));
    }

    private function passedSpamCheck(): Closure
    {
        return static fn ($checks) => $checks
            ->selectRaw('1')
            ->from('event_spam_checks')
            ->whereColumn('event_spam_checks.event_id', 'events.id')
            ->whereIn('event_spam_checks.status', [EventSpamCheckStatus::CLEAN->name, EventSpamCheckStatus::APPROVED->name]);
    }

    private function orphanedBefore(): string
    {
        return now()->subMinutes(self::ORPHANED_DRAFT_MINUTES)->toDateTimeString();
    }

    private function liveUpcomingEvents(int $organizerId, CarbonInterface $startingAfter): Builder
    {
        return $this->db->table('events')
            ->where('events.organizer_id', $organizerId)
            ->where('events.status', EventStatus::LIVE->name)
            ->whereNull('events.deleted_at')
            ->whereExists(static fn ($occurrences) => $occurrences
                ->selectRaw('1')
                ->from('event_occurrences')
                ->whereColumn('event_occurrences.event_id', 'events.id')
                ->whereNull('event_occurrences.deleted_at')
                ->where('event_occurrences.status', EventOccurrenceStatus::ACTIVE->name)
                ->where('event_occurrences.start_date', '>', $startingAfter));
    }

    public function findFinishedOccurrences(CrmAutomationDomainObject $automation, CarbonInterface $now): Collection
    {
        $finishedBefore = $now->copy()->subDays($automation->getDelayDays());
        $notOlderThan = $finishedBefore->copy()->subDays(7);

        return $this->runQuery(fn () => collect($this->db->select("
            SELECT eo.id, eo.event_id, eo.start_date
            FROM event_occurrences eo
            JOIN events e ON e.id = eo.event_id
            WHERE e.organizer_id = :organizer
              AND e.deleted_at IS NULL
              AND e.status IN (:live, :archived)
              AND eo.deleted_at IS NULL
              AND eo.status = :active
              AND coalesce(eo.end_date, eo.start_date) <= :finished_before
              AND coalesce(eo.end_date, eo.start_date) >= :not_older_than
              AND coalesce(eo.end_date, eo.start_date) >= :enabled_at
              AND NOT EXISTS (
                  SELECT 1 FROM crm_campaigns c
                  WHERE c.automation_id = :automation AND c.automation_run_key = 'occurrence:' || eo.id
                    AND (c.status <> :draft OR c.created_at >= :orphaned_before)
              )
            ORDER BY eo.id
            LIMIT :limit
        ", [
            'organizer' => $automation->getOrganizerId(),
            'automation' => $automation->getId(),
            'draft' => CrmCampaignStatus::DRAFT->value,
            'orphaned_before' => $this->orphanedBefore(),
            'active' => EventOccurrenceStatus::ACTIVE->name,
            'live' => EventStatus::LIVE->name,
            'archived' => EventStatus::ARCHIVED->name,
            'finished_before' => $finishedBefore->toDateTimeString(),
            'not_older_than' => $notOlderThan->toDateTimeString(),
            'enabled_at' => $automation->getEnabledAt(),
            'limit' => self::MAX_EVENTS_PER_RUN,
        ])));
    }

    public function findNextEndingOccurrence(int $organizerId, CarbonInterface $now): ?object
    {
        return $this->runQuery(fn () => $this->db->table('event_occurrences')
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->where('events.organizer_id', $organizerId)
            ->where('events.status', EventStatus::LIVE->name)
            ->whereNull('events.deleted_at')
            ->whereNull('event_occurrences.deleted_at')
            ->where('event_occurrences.status', EventOccurrenceStatus::ACTIVE->name)
            ->whereRaw('coalesce(event_occurrences.end_date, event_occurrences.start_date) >= ?', [$now])
            ->orderByRaw('coalesce(event_occurrences.end_date, event_occurrences.start_date), event_occurrences.id')
            ->first(['event_occurrences.id', 'event_occurrences.event_id', 'event_occurrences.start_date', 'event_occurrences.end_date']));
    }

    public function findAbandonedCheckoutContacts(CrmAutomationDomainObject $automation, CarbonInterface $abandonedFrom, CarbonInterface $abandonedUntil): Collection
    {
        return $this->runQuery(fn () => collect($this->abandonedCheckoutQuery('
            SELECT DISTINCT o.event_id, ce.contact_id
        ', '
            AND NOT EXISTS (
                SELECT 1 FROM crm_campaign_recipients reached
                JOIN crm_campaigns reached_campaign ON reached_campaign.id = reached.campaign_id
                WHERE reached_campaign.automation_id = :automation
                  AND reached_campaign.event_id = o.event_id
                  AND reached.contact_id = ce.contact_id
            )
            ORDER BY o.event_id, ce.contact_id
            LIMIT :limit
        ', $automation->getOrganizerId(), $abandonedFrom, $abandonedUntil, [
            'automation' => $automation->getId(),
            'limit' => self::MAX_ABANDONED_CONTACTS_PER_RUN,
        ])))->map(static fn (object $row) => (object) ['event_id' => (int) $row->event_id, 'contact_id' => (int) $row->contact_id]);
    }

    /**
     * @return object[]
     */
    private function abandonedCheckoutQuery(string $select, string $suffix, int $organizerId, CarbonInterface $from, CarbonInterface $until, array $bindings): array
    {
        return $this->db->select($select.'
            FROM orders o
            JOIN events e ON e.id = o.event_id
            JOIN crm_contact_emails ce ON ce.organizer_id = :email_organizer AND ce.email = '.self::ORDER_EMAIL.'
            JOIN crm_contacts c ON c.id = ce.contact_id AND c.email_marketing_status = :subscribed
            WHERE e.organizer_id = :organizer
              AND e.status = :live
              AND e.deleted_at IS NULL
              AND o.deleted_at IS NULL
              AND o.email IS NOT NULL
              AND o.box_office_id IS NULL
              AND o.payment_link_created_at IS NULL
              AND o.is_manually_created = false
              AND (
                  (o.status = :abandoned AND o.updated_at > :abandoned_from AND o.updated_at <= :abandoned_until)
                  OR (o.status = :reserved AND o.reserved_until > :reserved_from AND o.reserved_until <= :reserved_until)
              )
              AND NOT EXISTS (SELECT 1 FROM waitlist_entries w WHERE w.order_id = o.id)
              AND EXISTS (
                  SELECT 1 FROM event_occurrences eo
                  WHERE eo.event_id = o.event_id AND eo.deleted_at IS NULL AND eo.status = :active AND eo.start_date > :now
              )
              AND NOT EXISTS (
                  SELECT 1 FROM orders later
                  JOIN crm_contact_emails later_email ON later_email.organizer_id = :later_organizer AND later_email.email = '.self::LATER_ORDER_EMAIL.'
                  WHERE later.event_id = o.event_id
                    AND later.deleted_at IS NULL
                    AND later.status IN (:completed, :awaiting)
                    AND later.created_at >= o.created_at
                    AND later_email.contact_id = ce.contact_id
              )
        '.$suffix, [
            'email_organizer' => $organizerId,
            'subscribed' => CrmEmailMarketingStatus::SUBSCRIBED->value,
            'organizer' => $organizerId,
            'live' => EventStatus::LIVE->name,
            'abandoned' => OrderStatus::ABANDONED->name,
            'abandoned_from' => $from->toDateTimeString(),
            'abandoned_until' => $until->toDateTimeString(),
            'reserved' => OrderStatus::RESERVED->name,
            'reserved_from' => $from->toDateTimeString(),
            'reserved_until' => $until->toDateTimeString(),
            'active' => EventOccurrenceStatus::ACTIVE->name,
            'now' => now()->toDateTimeString(),
            'later_organizer' => $organizerId,
            'completed' => OrderStatus::COMPLETED->name,
            'awaiting' => OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            ...$bindings,
        ]);
    }

    public function findOccurrencesDueAReminder(CrmAutomationDomainObject $automation, int $daysBefore, int $sendHour, CarbonInterface $notSentBefore, CarbonInterface $now): Collection
    {
        return $this->runQuery(fn () => collect($this->db->select("
            SELECT due.id, due.event_id, due.start_date
            FROM (
                SELECT eo.id, eo.event_id, eo.start_date, eo.created_at,
                    (
                        date_trunc('day', eo.start_date AT TIME ZONE 'UTC' AT TIME ZONE coalesce(e.timezone, 'UTC'))
                        - CAST(:days_before AS integer) * interval '1 day'
                        + CAST(:send_hour AS integer) * interval '1 hour'
                    ) AT TIME ZONE coalesce(e.timezone, 'UTC') AT TIME ZONE 'UTC' AS send_at
                FROM event_occurrences eo
                JOIN events e ON e.id = eo.event_id
                WHERE e.organizer_id = :organizer
                  AND e.status = :live
                  AND e.deleted_at IS NULL
                  AND eo.deleted_at IS NULL
                  AND eo.status = :active
                  AND eo.start_date > :starts_after
                  AND eo.start_date <= :starts_before
                  AND NOT EXISTS (
                      SELECT 1 FROM crm_campaigns c
                      WHERE c.automation_id = :automation AND c.automation_run_key = 'before:' || eo.id
                        AND (c.status <> :draft OR c.created_at >= :orphaned_before)
                  )
            ) due
            WHERE due.send_at <= :now
              AND due.send_at > :not_sent_before
              AND due.send_at >= :enabled_at
              AND due.send_at >= due.created_at
            ORDER BY due.start_date, due.id
            LIMIT :limit
        ", [
            'days_before' => $daysBefore,
            'send_hour' => $sendHour,
            'organizer' => $automation->getOrganizerId(),
            'live' => EventStatus::LIVE->name,
            'active' => EventOccurrenceStatus::ACTIVE->name,
            'starts_after' => $notSentBefore->copy()->addDays($daysBefore)->subHours($sendHour + 1)->toDateTimeString(),
            'starts_before' => $now->copy()->addDays($daysBefore)->addHours(24 - $sendHour + 1)->toDateTimeString(),
            'automation' => $automation->getId(),
            'draft' => CrmCampaignStatus::DRAFT->value,
            'orphaned_before' => $this->orphanedBefore(),
            'now' => $now->toDateTimeString(),
            'not_sent_before' => $notSentBefore->toDateTimeString(),
            'enabled_at' => $automation->getEnabledAt(),
            'limit' => self::MAX_EVENTS_PER_RUN,
        ])));
    }

    public function findUpcomingOccurrences(int $organizerId, CarbonInterface $now): Collection
    {
        return $this->runQuery(fn () => $this->db->table('event_occurrences')
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->where('events.organizer_id', $organizerId)
            ->where('events.status', EventStatus::LIVE->name)
            ->whereNull('events.deleted_at')
            ->whereNull('event_occurrences.deleted_at')
            ->where('event_occurrences.status', EventOccurrenceStatus::ACTIVE->name)
            ->where('event_occurrences.start_date', '>', $now)
            ->orderBy('event_occurrences.start_date')
            ->orderBy('event_occurrences.id')
            ->limit(self::MAX_EVENTS_PER_RUN)
            ->get(['event_occurrences.id', 'event_occurrences.event_id', 'event_occurrences.start_date', 'events.timezone']));
    }

    public function replaceMembers(int $automationId, int $organizerId, CompiledSegmentDTO $segment): int
    {
        return $this->runQuery(fn () => $this->db->transaction(function () use ($automationId, $organizerId, $segment): int {
            $this->db->table('crm_automation_memberships')->where('automation_id', $automationId)->delete();

            return $this->db->table('crm_automation_memberships')->insertUsing(
                ['automation_id', 'contact_id'],
                $this->members($organizerId, $segment)->selectRaw('CAST(? AS bigint), id', [$automationId]),
            );
        }));
    }

    public function findEntrants(int $automationId, int $organizerId, CompiledSegmentDTO $segment, int $limit): array
    {
        return $this->runQuery(fn () => $this->members($organizerId, $segment)
            ->whereNotExists(static fn ($memberships) => $memberships
                ->selectRaw('1')
                ->from('crm_automation_memberships')
                ->where('crm_automation_memberships.automation_id', $automationId)
                ->whereColumn('crm_automation_memberships.contact_id', 'crm_contacts.id'))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all());
    }

    public function addMembersFromLatestLaunchedCampaign(int $automationId, CarbonInterface $launchedSince): void
    {
        $this->runQuery(fn () => $this->db->statement('
            INSERT INTO crm_automation_memberships (automation_id, contact_id)
            SELECT :automation, listed.contact_id
            FROM crm_campaign_audience_contacts listed
            WHERE listed.campaign_id = (
                SELECT c.id FROM crm_campaigns c
                WHERE c.automation_id = :campaign_automation
                  AND c.status <> :draft
                  AND c.created_at >= :launched_since
                ORDER BY c.id DESC
                LIMIT 1
            )
            ON CONFLICT (automation_id, contact_id) DO NOTHING
        ', [
            'automation' => $automationId,
            'campaign_automation' => $automationId,
            'draft' => CrmCampaignStatus::DRAFT->value,
            'launched_since' => $launchedSince->toDateTimeString(),
        ]));
    }

    public function addMembers(int $automationId, array $contactIds): void
    {
        foreach (array_chunk($contactIds, 1000) as $chunk) {
            $this->runQuery(fn () => $this->db->table('crm_automation_memberships')->insertOrIgnore(array_map(
                static fn (int $contactId) => ['automation_id' => $automationId, 'contact_id' => $contactId],
                $chunk,
            )));
        }
    }

    public function clearMembers(int $automationId): void
    {
        $this->runQuery(fn () => $this->db->table('crm_automation_memberships')->where('automation_id', $automationId)->delete());
    }

    private function members(int $organizerId, CompiledSegmentDTO $segment): Builder
    {
        return $this->db->table('crm_contacts')
            ->where('organizer_id', $organizerId)
            ->whereRaw($segment->sql, $segment->bindings);
    }
}
