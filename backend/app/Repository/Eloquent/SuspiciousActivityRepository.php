<?php

namespace HiEvents\Repository\Eloquent;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\Repository\Interfaces\SuspiciousActivityRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class SuspiciousActivityRepository implements SuspiciousActivityRepositoryInterface
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function countAccountsCreatedBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->table('accounts')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();
    }

    public function findRecentAccountNamesAndEmailDomains(CarbonInterface $since, int $limit): Collection
    {
        return $this->table('accounts')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->selectRaw("left(name, 80) as name, split_part(email, '@', 2) as email_domain, count(*) as total")
            ->groupByRaw("left(name, 80), split_part(email, '@', 2)")
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    public function findUsersWithSuspiciousNames(CarbonInterface $since, string $pattern, int $limit): Collection
    {
        return $this->table('users')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->where(fn (Builder $query) => $query
                ->whereRaw('first_name ~* ?', [$pattern])
                ->orWhereRaw("coalesce(last_name, '') ~* ?", [$pattern]))
            ->selectRaw("id, left(first_name || ' ' || coalesce(last_name, ''), 120) as name, created_at")
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function findAccountsWithSuspiciousNames(CarbonInterface $since, string $pattern, int $limit): Collection
    {
        return $this->findNamedRows('accounts', 'name', $since, $pattern, $limit);
    }

    public function findOrganizersWithSuspiciousNames(CarbonInterface $since, string $pattern, int $limit): Collection
    {
        return $this->findNamedRows('organizers', 'name', $since, $pattern, $limit, 'updated_at');
    }

    public function findEventsWithSuspiciousTitles(CarbonInterface $since, string $pattern, int $limit): Collection
    {
        return $this->findNamedRows('events', 'title', $since, $pattern, $limit, 'updated_at');
    }

    public function findAccountsWithInviteBursts(CarbonInterface $since, int $threshold): Collection
    {
        return $this->table('account_users')
            ->whereNull('deleted_at')
            ->whereNotNull('invited_by_user_id')
            ->where('created_at', '>=', $since)
            ->selectRaw('account_id, count(*) as total')
            ->groupBy('account_id')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->orderByDesc('total')
            ->get();
    }

    public function findUnpublishedEventsWithOrderBursts(CarbonInterface $since, array $statuses, int $attendeeThreshold): Collection
    {
        return $this->table('attendees')
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->whereNull('attendees.deleted_at')
            ->whereIn('events.status', $statuses)
            ->where('attendees.created_at', '>=', $since)
            ->selectRaw('events.id as event_id, events.account_id, events.status, count(distinct orders.id) as orders, count(*) as attendees, count(distinct lower(attendees.email)) as distinct_emails')
            ->groupBy('events.id', 'events.account_id', 'events.status')
            ->havingRaw('count(*) >= ?', [$attendeeThreshold])
            ->orderByDesc('attendees')
            ->get();
    }

    public function findEventsWithFailedPaymentBursts(CarbonInterface $since, int $threshold): Collection
    {
        return $this->table('orders')
            ->whereNull('deleted_at')
            ->where('payment_status', OrderPaymentStatus::PAYMENT_FAILED->name)
            ->where('updated_at', '>=', $since)
            ->selectRaw('event_id, count(*) as total')
            ->groupBy('event_id')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->orderByDesc('total')
            ->get();
    }

    public function findEventsWithWaitlistBursts(CarbonInterface $since, int $threshold): Collection
    {
        return $this->table('waitlist_entries')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->selectRaw('event_id, count(*) as total')
            ->groupBy('event_id')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->orderByDesc('total')
            ->get();
    }

    public function findLiveEventsWithoutSpamCheck(CarbonInterface $createdAfter, CarbonInterface $createdBefore, int $limit): Collection
    {
        return $this->table('events')
            ->whereNull('events.deleted_at')
            ->where('events.status', EventStatus::LIVE->name)
            ->where('events.created_at', '>=', $createdAfter)
            ->where('events.created_at', '<', $createdBefore)
            ->whereNotExists(fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('event_spam_checks')
                ->whereColumn('event_spam_checks.event_id', 'events.id'))
            ->selectRaw('events.id, events.account_id, left(events.title, 120) as title')
            ->orderByDesc('events.id')
            ->limit($limit)
            ->get();
    }

    public function findEmailCategoryStats(CarbonInterface $since): Collection
    {
        return $this->table('email_deliveries')
            ->where('created_at', '>=', $since)
            ->selectRaw("coalesce(category, 'unknown') as category, count(*) as total, count(bounced_at) as bounced, count(complained_at) as complained")
            ->groupByRaw("coalesce(category, 'unknown')")
            ->get();
    }

    private function findNamedRows(
        string $table,
        string $column,
        CarbonInterface $since,
        string $pattern,
        int $limit,
        string $timestampColumn = 'created_at',
    ): Collection {
        return $this->table($table)
            ->whereNull('deleted_at')
            ->where($timestampColumn, '>=', $since)
            ->whereRaw("$column ~* ?", [$pattern])
            ->selectRaw("id, left($column, 120) as name, $timestampColumn as changed_at")
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function table(string $table): Builder
    {
        return $this->db->connection()->table($table);
    }
}
