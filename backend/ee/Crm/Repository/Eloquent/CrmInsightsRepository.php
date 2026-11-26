<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmActivityType;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmInsightsRepositoryInterface;
use HiEvents\Models\CrmContact;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmContactDomainObject>
 */
class CrmInsightsRepository extends BaseRepository implements CrmInsightsRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmContact::class;
    }

    public function getDomainObject(): string
    {
        return CrmContactDomainObject::class;
    }

    public function getAudienceTotals(int $organizerId, CarbonInterface $now): object
    {
        return $this->runQuery(fn () => $this->db->selectOne('
            SELECT
                count(*) AS contacts_count,
                count(*) FILTER (WHERE email_marketing_status = :subscribed) AS mailable_count,
                count(*) FILTER (WHERE orders_count >= 1) AS buyers_count,
                count(*) FILTER (WHERE first_seen_at >= :recent) AS new_contacts_count,
                coalesce(sum(orders_count), 0) AS orders_count,
                coalesce(sum(tickets_count), 0) AS tickets_count,
                coalesce(avg(events_count) FILTER (WHERE orders_count >= 1), 0) AS average_events_per_buyer
            FROM crm_contacts
            WHERE organizer_id = :organizer
        ', [
            'organizer' => $organizerId,
            'subscribed' => CrmEmailMarketingStatus::SUBSCRIBED->value,
            'recent' => $now->copy()->subDays(30)->toDateTimeString(),
        ]));
    }

    public function getMonthlyBuyers(int $organizerId, string $timezone, CarbonInterface $since): Collection
    {
        return $this->runQuery(fn () => collect($this->db->select("
            WITH orders AS (
                SELECT
                    a.contact_id,
                    date_trunc('month', a.occurred_at AT TIME ZONE 'UTC' AT TIME ZONE :timezone) AS month,
                    date_trunc('month', (min(a.occurred_at) OVER (PARTITION BY a.contact_id)) AT TIME ZONE 'UTC' AT TIME ZONE :first_timezone) AS first_month
                FROM crm_contact_activities a
                WHERE a.organizer_id = :organizer AND a.type = :type
            )
            SELECT
                to_char(month, 'YYYY-MM') AS month,
                count(DISTINCT contact_id) FILTER (WHERE month = first_month) AS new_buyers,
                count(DISTINCT contact_id) FILTER (WHERE month > first_month) AS returning_buyers
            FROM orders
            WHERE month >= date_trunc('month', CAST(:since AS timestamp))
            GROUP BY month
            ORDER BY month
        ", [
            'organizer' => $organizerId,
            'type' => CrmActivityType::ORDER_COMPLETED->value,
            'timezone' => $timezone,
            'first_timezone' => $timezone,
            'since' => $since->toDateTimeString(),
        ])));
    }

    public function getRevenueConcentration(int $organizerId): Collection
    {
        return $this->runQuery(fn () => collect($this->db->select('
            WITH ranked AS (
                SELECT
                    currency,
                    total_gross - total_refunded AS net,
                    ntile(10) OVER (PARTITION BY currency ORDER BY total_gross - total_refunded DESC) AS decile
                FROM crm_contact_spend
                WHERE organizer_id = :organizer AND total_gross - total_refunded > 0
            )
            SELECT
                currency,
                sum(net) AS total_net,
                coalesce(sum(net) FILTER (WHERE decile = 1), 0) AS top_decile_net
            FROM ranked
            GROUP BY currency
            ORDER BY sum(net) DESC
        ', ['organizer' => $organizerId])));
    }

    public function getRetentionCohorts(int $organizerId, string $timezone, CarbonInterface $since): Collection
    {
        return $this->runQuery(fn () => collect($this->db->select("
            WITH orders AS (
                SELECT DISTINCT
                    a.contact_id,
                    date_trunc('month', a.occurred_at AT TIME ZONE 'UTC' AT TIME ZONE :timezone) AS month
                FROM crm_contact_activities a
                WHERE a.organizer_id = :organizer AND a.type = :type
            ),
            firsts AS (
                SELECT contact_id, min(month) AS cohort FROM orders GROUP BY contact_id
            )
            SELECT
                to_char(f.cohort, 'YYYY-MM') AS cohort,
                (extract(year FROM age(o.month, f.cohort)) * 12 + extract(month FROM age(o.month, f.cohort)))::int AS month_offset,
                count(DISTINCT o.contact_id) AS contacts
            FROM firsts f
            JOIN orders o ON o.contact_id = f.contact_id
            WHERE f.cohort >= date_trunc('month', CAST(:since AS timestamp))
            GROUP BY f.cohort, month_offset
            ORDER BY f.cohort, month_offset
        ", [
            'organizer' => $organizerId,
            'type' => CrmActivityType::ORDER_COMPLETED->value,
            'timezone' => $timezone,
            'since' => $since->toDateTimeString(),
        ])));
    }

    public function getLeadTimeBuckets(int $organizerId): Collection
    {
        return $this->runQuery(fn () => collect($this->db->select('
            SELECT bucket, count(*) AS tickets
            FROM (
                SELECT CASE
                    WHEN days < 1 THEN 0
                    WHEN days < 3 THEN 1
                    WHEN days < 7 THEN 2
                    WHEN days < 14 THEN 3
                    WHEN days < 30 THEN 4
                    WHEN days < 60 THEN 5
                    ELSE 6
                END AS bucket
                FROM (
                    SELECT extract(epoch FROM (eo.start_date - a.occurred_at)) / 86400 AS days
                    FROM crm_contact_activities a
                    JOIN event_occurrences eo ON eo.id = a.event_occurrence_id
                    WHERE a.organizer_id = :organizer AND a.type = :type
                ) lead_times
                WHERE days >= 0
            ) bucketed
            GROUP BY bucket
            ORDER BY bucket
        ', ['organizer' => $organizerId, 'type' => CrmActivityType::TICKET_HELD->value])));
    }

    public function eventBelongsToOrganizer(int $organizerId, int $eventId): bool
    {
        return $this->runQuery(fn () => $this->db->table('events')
            ->where('id', $eventId)
            ->where('organizer_id', $organizerId)
            ->whereNull('deleted_at')
            ->exists());
    }

    public function getEventAudience(int $organizerId, int $eventId): object
    {
        return $this->runQuery(fn () => $this->db->selectOne('
            WITH holders AS (
                SELECT
                    a.contact_id,
                    count(*) AS tickets,
                    min(a.occurred_at) AS first_ticket_at,
                    (array_agg(a.order_id ORDER BY a.occurred_at, a.order_id))[1] AS first_order_id
                FROM crm_contact_activities a
                WHERE a.organizer_id = :organizer AND a.type = :type AND a.event_id = :event
                GROUP BY a.contact_id
            )
            SELECT
                count(*) AS contacts_count,
                coalesce(sum(h.tickets), 0) AS tickets_count,
                count(*) FILTER (WHERE c.email_marketing_status = :subscribed) AS mailable_count,
                (
                    SELECT count(*) FROM crm_contact_activities scans
                    WHERE scans.organizer_id = :scan_organizer AND scans.type = :scan_type AND scans.event_id = :scan_event
                ) AS checked_in_count,
                count(*) FILTER (WHERE EXISTS (
                    SELECT 1 FROM crm_contact_activities earlier
                    WHERE earlier.contact_id = h.contact_id
                      AND earlier.type = :earlier_type
                      AND earlier.event_id <> :other_event
                      AND (earlier.occurred_at, earlier.order_id) < (h.first_ticket_at, h.first_order_id)
                )) AS returning_count
            FROM holders h
            JOIN crm_contacts c ON c.id = h.contact_id
        ', [
            'organizer' => $organizerId,
            'type' => CrmActivityType::TICKET_HELD->value,
            'earlier_type' => CrmActivityType::TICKET_HELD->value,
            'event' => $eventId,
            'other_event' => $eventId,
            'subscribed' => CrmEmailMarketingStatus::SUBSCRIBED->value,
            'scan_organizer' => $organizerId,
            'scan_type' => CrmActivityType::CHECKED_IN->value,
            'scan_event' => $eventId,
        ]));
    }

    public function getEventTopContacts(int $organizerId, int $eventId, int $limit): Collection
    {
        return $this->runQuery(fn () => $this->db->table('crm_contacts')
            ->where('crm_contacts.organizer_id', $organizerId)
            ->where('crm_contacts.events_count', '>=', 2)
            ->whereExists(static fn ($tickets) => $tickets
                ->selectRaw('1')
                ->from('crm_contact_activities')
                ->whereColumn('crm_contact_activities.contact_id', 'crm_contacts.id')
                ->where('crm_contact_activities.type', CrmActivityType::TICKET_HELD->value)
                ->where('crm_contact_activities.event_id', $eventId))
            ->orderByDesc('events_count')
            ->orderByDesc('attended_count')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'email', 'first_name', 'last_name', 'events_count', 'orders_count', 'attended_count', 'last_order_at']));
    }

    public function getEventOverlaps(int $organizerId, int $eventId, int $limit): Collection
    {
        return $this->runQuery(fn () => collect($this->db->select('
            SELECT other.event_id, e.title, count(DISTINCT other.contact_id) AS shared_contacts
            FROM crm_contact_activities mine
            JOIN crm_contact_activities other
              ON other.contact_id = mine.contact_id AND other.type = mine.type AND other.event_id <> mine.event_id
            JOIN events e ON e.id = other.event_id AND e.deleted_at IS NULL
            WHERE mine.organizer_id = :organizer AND mine.type = :type AND mine.event_id = :event
            GROUP BY other.event_id, e.title
            ORDER BY shared_contacts DESC, other.event_id DESC
            LIMIT :limit
        ', [
            'organizer' => $organizerId,
            'type' => CrmActivityType::TICKET_HELD->value,
            'event' => $eventId,
            'limit' => $limit,
        ])));
    }

    public function getExpectedTurnout(int $organizerId, int $eventId, CarbonInterface $now): object
    {
        return $this->runQuery(fn () => $this->db->selectOne('
            WITH holders AS (
                SELECT a.contact_id, count(*) AS tickets
                FROM crm_contact_activities a
                WHERE a.organizer_id = :holder_organizer AND a.type = :holder_type AND a.event_id = :event
                GROUP BY a.contact_id
            ),
            scanned_occurrences AS (
                SELECT DISTINCT a.event_id, a.event_occurrence_id
                FROM crm_contact_activities a
                WHERE a.organizer_id = :organizer AND a.type = :check_in_type AND a.event_id <> :scan_event
            ),
            history AS (
                SELECT
                    t.contact_id,
                    count(DISTINCT (t.event_id, t.event_occurrence_id)) AS held,
                    count(DISTINCT (t.event_id, t.event_occurrence_id)) FILTER (WHERE EXISTS (
                        SELECT 1 FROM crm_contact_activities ci
                        WHERE ci.contact_id = t.contact_id AND ci.type = :own_check_in_type
                          AND ci.event_id = t.event_id AND ci.event_occurrence_id = t.event_occurrence_id
                    )) AS attended
                FROM crm_contact_activities t
                JOIN scanned_occurrences so ON so.event_id = t.event_id AND so.event_occurrence_id = t.event_occurrence_id
                JOIN event_occurrences eo ON eo.id = t.event_occurrence_id AND coalesce(eo.end_date, eo.start_date) < :now
                WHERE t.organizer_id = :history_organizer AND t.type = :ticket_type
                  AND t.contact_id IN (SELECT contact_id FROM holders)
                GROUP BY t.contact_id
            ),
            baseline AS (
                SELECT CASE WHEN sum(held) > 0 THEN sum(attended)::numeric / sum(held) END AS show_rate FROM history
            )
            SELECT
                (SELECT show_rate FROM baseline) AS baseline_show_rate,
                coalesce(sum(h.tickets), 0) AS tickets_count,
                sum(h.tickets * coalesce(
                    (hist.attended + (SELECT show_rate FROM baseline))::numeric / (hist.held + 1),
                    (SELECT show_rate FROM baseline)
                )) AS expected_tickets,
                count(*) FILTER (WHERE hist.held >= 2 AND hist.attended = 0) AS likely_no_show_contacts
            FROM holders h
            LEFT JOIN history hist ON hist.contact_id = h.contact_id
        ', [
            'organizer' => $organizerId,
            'history_organizer' => $organizerId,
            'holder_organizer' => $organizerId,
            'check_in_type' => CrmActivityType::CHECKED_IN->value,
            'own_check_in_type' => CrmActivityType::CHECKED_IN->value,
            'ticket_type' => CrmActivityType::TICKET_HELD->value,
            'holder_type' => CrmActivityType::TICKET_HELD->value,
            'scan_event' => $eventId,
            'event' => $eventId,
            'now' => $now->toDateTimeString(),
        ]));
    }

    public function getEventOptions(int $organizerId, ?string $query, array $includeIds, int $limit): Collection
    {
        $cancelled = EventOccurrenceStatus::CANCELLED->name;
        $ranked = "
            SELECT e.id, e.title, e.status, e.timezone, upcoming.next_start, past.last_start
            FROM events e
            LEFT JOIN LATERAL (
                SELECT min(o.start_date) AS next_start
                FROM event_occurrences o
                WHERE o.event_id = e.id AND o.deleted_at IS NULL AND o.status <> '$cancelled'
                  AND coalesce(o.end_date, o.start_date) >= ?
            ) upcoming ON true
            LEFT JOIN LATERAL (
                SELECT max(o.start_date) AS last_start
                FROM event_occurrences o
                WHERE o.event_id = e.id AND o.deleted_at IS NULL
            ) past ON true
            WHERE e.organizer_id = ? AND e.deleted_at IS NULL
        ";
        $order = 'ORDER BY next_start IS NULL, next_start, last_start DESC NULLS LAST, id DESC';
        $bindings = [now()->toDateTimeString(), $organizerId];

        $matches = 'SELECT * FROM ranked';
        if ($query !== null && trim($query) !== '') {
            $matches .= ' WHERE title ILIKE ?';
            $bindings[] = $this->likeTerm($query);
        }
        $options = "($matches $order LIMIT ?)";
        $bindings[] = $limit;

        $includeIds = array_values(array_unique(array_map('intval', $includeIds)));
        if ($includeIds !== []) {
            $options .= ' UNION (SELECT * FROM ranked WHERE id IN ('.implode(', ', array_fill(0, count($includeIds), '?')).'))';
            array_push($bindings, ...$includeIds);
        }

        return $this->runQuery(fn () => collect($this->db->select("WITH ranked AS ($ranked) SELECT * FROM ($options) options $order", $bindings)));
    }

    public function getProductOptions(int $organizerId, ?string $query, array $includeIds, int $limit): Collection
    {
        $base = fn () => $this->db->table('products')
            ->join('events', 'events.id', '=', 'products.event_id')
            ->where('events.organizer_id', $organizerId)
            ->whereNull('events.deleted_at')
            ->whereNull('products.deleted_at')
            ->select(['products.id', 'products.title', 'products.event_id', 'events.title AS event_title']);

        $term = $query !== null && trim($query) !== '' ? $this->likeTerm($query) : null;

        $matches = $base()
            ->when($term !== null, static fn ($builder) => $builder->where(static fn ($match) => $match
                ->where('products.title', 'ilike', $term)
                ->orWhere('events.title', 'ilike', $term)))
            ->orderByDesc('products.event_id')
            ->orderBy('products.order')
            ->orderBy('products.id')
            ->limit($limit);

        $includeIds = array_values(array_unique(array_map('intval', $includeIds)));

        if ($includeIds !== []) {
            $matches->union($base()->whereIn('products.id', $includeIds))
                ->orderByDesc('event_id')
                ->orderBy('id');
        }

        return $this->runQuery(fn () => $matches->get()->unique('id')->values());
    }

    public function getLiveEventOptInCounts(int $organizerId, CarbonInterface $now): object
    {
        return $this->runQuery(fn () => $this->liveUpcomingEvents($organizerId, $now)
            ->selectRaw('count(*) AS live_events_count')
            ->selectRaw('count(*) FILTER (WHERE coalesce(s.show_marketing_opt_in, true)) AS collecting_count')
            ->first());
    }

    public function findLiveEventsNotCollectingOptIn(int $organizerId, CarbonInterface $now, int $limit): Collection
    {
        return $this->runQuery(fn () => $this->liveUpcomingEvents($organizerId, $now)
            ->where('s.show_marketing_opt_in', false)
            ->orderByDesc('e.id')
            ->limit($limit)
            ->get(['e.id', 'e.title']));
    }

    private function liveUpcomingEvents(int $organizerId, CarbonInterface $now): Builder
    {
        return $this->db->table('events AS e')
            ->leftJoin('event_settings AS s', 's.event_id', '=', 'e.id')
            ->where('e.organizer_id', $organizerId)
            ->whereNull('e.deleted_at')
            ->where('e.status', EventStatus::LIVE->name)
            ->whereExists(static fn ($occurrences) => $occurrences
                ->selectRaw('1')
                ->from('event_occurrences AS o')
                ->whereColumn('o.event_id', 'e.id')
                ->whereNull('o.deleted_at')
                ->where('o.status', '<>', EventOccurrenceStatus::CANCELLED->name)
                ->whereRaw('coalesce(o.end_date, o.start_date) >= ?', [$now->toDateTimeString()]));
    }

    public function getLocationOptions(int $organizerId): Collection
    {
        return $this->runQuery(fn () => $this->db->table('locations')
            ->where('organizer_id', $organizerId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name']));
    }

    public function getCurrencies(int $organizerId): array
    {
        return $this->runQuery(fn () => $this->db->table('crm_contact_spend')
            ->where('organizer_id', $organizerId)
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all());
    }

    private function likeTerm(string $query): string
    {
        return '%'.addcslashes(trim($query), '\\%_').'%';
    }
}
