<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Event;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<EventDomainObject>
 */
class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    protected function getModel(): string
    {
        return Event::class;
    }

    public function getDomainObject(): string
    {
        return EventDomainObject::class;
    }

    public function findEventsForOrganizer(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where[] = static function (Builder $builder) use ($accountId, $organizerId) {
            $builder
                ->whereIn(EventDomainObjectAbstract::STATUS, [
                    EventStatus::LIVE->name,
                    EventStatus::DRAFT->name,
                    EventStatus::PENDING_MANUAL_REVIEW->name,
                ])
                ->where(EventDomainObjectAbstract::ORGANIZER_ID, $organizerId)
                ->where(EventDomainObjectAbstract::ACCOUNT_ID, $accountId);
        };

        return $this->findEvents($where, $params);
    }

    public function findEvents(array $where, QueryParamsDTO $params): LengthAwarePaginator
    {
        if (! empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(EventDomainObjectAbstract::TITLE, 'ilike', '%'.$params->query.'%');
            };
        }

        $upcomingEventsFilter = $params->query_params->get('eventsStatus') === 'upcoming';
        $endedEventsFilter = $params->query_params->get('eventsStatus') === 'ended';

        if (! empty($params->filter_fields)) {
            $this->applyFilterFields($params, EventDomainObject::getAllowedFilterFields());
        }

        if ($upcomingEventsFilter) {
            $where[] = static function (Builder $builder) {
                $builder
                    ->where(EventDomainObjectAbstract::STATUS, '!=', EventStatus::ARCHIVED->getName())
                    ->where(function (Builder $eventQuery) {
                        $eventQuery
                            ->whereNotExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('event_occurrences')
                                    ->whereColumn('event_occurrences.event_id', 'events.id')
                                    ->whereNull('event_occurrences.deleted_at');
                            })
                            ->orWhereExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('event_occurrences')
                                    ->whereColumn('event_occurrences.event_id', 'events.id')
                                    ->whereNull('event_occurrences.deleted_at')
                                    ->whereRaw('COALESCE(event_occurrences.end_date, event_occurrences.start_date) >= ?', [now()]);
                            });
                    });
            };
        }

        if ($endedEventsFilter) {
            $where[] = static function (Builder $builder) {
                $builder
                    ->where(EventDomainObjectAbstract::STATUS, '!=', EventStatus::ARCHIVED->getName())
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('event_occurrences')
                            ->whereColumn('event_occurrences.event_id', 'events.id')
                            ->whereNull('event_occurrences.deleted_at');
                    })
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('event_occurrences')
                            ->whereColumn('event_occurrences.event_id', 'events.id')
                            ->whereNull('event_occurrences.deleted_at')
                            ->whereRaw('COALESCE(event_occurrences.end_date, event_occurrences.start_date) >= ?', [now()]);
                    });
            };
        }

        $sortColumn = $this->validateSortColumn($params->sort_by, EventDomainObject::class);
        $sortDirection = $this->validateSortDirection($params->sort_direction, EventDomainObject::class);

        if ($sortColumn === EventDomainObjectAbstract::START_DATE) {
            $this->applyOccurrenceStartDateSort($sortDirection, $upcomingEventsFilter, $endedEventsFilter);
        } else {
            $this->model = $this->model->orderBy($sortColumn, $sortDirection);
        }

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    private function applyOccurrenceStartDateSort(string $direction, bool $upcomingOnly, bool $endedOnly): void
    {
        $liveOccurrences = 'FROM event_occurrences eo WHERE eo.event_id = events.id AND eo.deleted_at IS NULL';
        $bindings = [];

        if ($upcomingOnly) {
            $sortDateSql = "SELECT MIN(eo.start_date) {$liveOccurrences} AND COALESCE(eo.end_date, eo.start_date) >= ?";
            $bindings[] = now();
        } elseif ($endedOnly) {
            $sortDateSql = "SELECT MAX(eo.start_date) {$liveOccurrences}";
        } else {
            $sortDateSql = "SELECT MIN(eo.start_date) {$liveOccurrences}";
        }

        $this->model = $this->model
            ->orderByRaw("({$sortDateSql}) {$direction} NULLS LAST", $bindings)
            ->orderBy(EventDomainObjectAbstract::ID);
    }

    public function getUpcomingEventsForAdmin(int $perPage): LengthAwarePaginator
    {
        $now = now();
        $next24Hours = now()->addDay();

        $this->model = $this->model
            ->select('events.*')
            ->whereExists(function ($query) use ($now, $next24Hours) {
                $query->select(DB::raw(1))
                    ->from('event_occurrences')
                    ->whereColumn('event_occurrences.event_id', 'events.id')
                    ->whereNull('event_occurrences.deleted_at')
                    ->where('event_occurrences.start_date', '>=', $now)
                    ->where('event_occurrences.start_date', '<=', $next24Hours)
                    ->where('event_occurrences.status', 'ACTIVE');
            })
            ->whereIn(EventDomainObjectAbstract::STATUS, [
                EventStatus::LIVE->name,
            ])
            ->orderBy(EventDomainObjectAbstract::CREATED_AT, 'desc');

        $this->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'));
        $this->loadRelation(new Relationship(AccountDomainObject::class, name: 'account'));
        $this->loadRelation(new Relationship(EventOccurrenceDomainObject::class));

        return $this->paginate($perPage);
    }

    public function getAllEventsForAdmin(
        ?string $search = null,
        int $perPage = 20,
        ?string $sortBy = 'created_at',
        ?string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        $this->model = $this->model
            ->select('events.*')
            ->withCount('attendees');

        if ($search) {
            $this->model = $this->model->where(function ($q) use ($search) {
                $q->where(EventDomainObjectAbstract::TITLE, 'ilike', '%'.$search.'%')
                    ->orWhereHas('organizer', function ($orgQuery) use ($search) {
                        $orgQuery->where('name', 'ilike', '%'.$search.'%');
                    });
            });
        }

        $allowedSortColumns = ['title', 'created_at', 'updated_at', 'start_date', 'end_date'];
        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'created_at';
        $sortDir = in_array(strtolower((string) $sortDirection), ['asc', 'desc'], true) ? strtolower($sortDirection) : 'desc';

        if ($sortColumn === 'start_date') {
            $this->model = $this->model->orderByRaw(
                "(SELECT MIN(eo.start_date) FROM event_occurrences eo WHERE eo.event_id = events.id AND eo.deleted_at IS NULL) {$sortDir} NULLS LAST"
            );
        } elseif ($sortColumn === 'end_date') {
            $this->model = $this->model->orderByRaw(
                "(SELECT MAX(COALESCE(eo.end_date, eo.start_date)) FROM event_occurrences eo WHERE eo.event_id = events.id AND eo.deleted_at IS NULL) {$sortDir} NULLS LAST"
            );
        } else {
            $this->model = $this->model->orderBy($sortColumn, $sortDir);
        }

        $this->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'));
        $this->loadRelation(new Relationship(AccountDomainObject::class, name: 'account'));
        $this->loadRelation(new Relationship(EventStatisticDomainObject::class, name: 'event_statistics'));
        $this->loadRelation(new Relationship(EventOccurrenceDomainObject::class));

        return $this->paginate($perPage);
    }

    public function getSitemapEvents(int $page, int $perPage): LengthAwarePaginator
    {
        return $this->handleResults($this->model
            ->select([
                'events.'.EventDomainObjectAbstract::ID,
                'events.'.EventDomainObjectAbstract::TITLE,
                'events.'.EventDomainObjectAbstract::UPDATED_AT,
            ])
            ->join('event_settings', 'events.id', '=', 'event_settings.event_id')
            ->where('events.'.EventDomainObjectAbstract::STATUS, EventStatus::LIVE->name)
            ->where('event_settings.'.EventSettingDomainObjectAbstract::ALLOW_SEARCH_ENGINE_INDEXING, true)
            ->whereNull('events.'.EventDomainObjectAbstract::DELETED_AT)
            ->orderBy('events.'.EventDomainObjectAbstract::ID)
            ->paginate($perPage, ['*'], 'page', $page));
    }

    public function findByIdLocked(int $id): EventDomainObject
    {
        $model = Event::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();

        return $this->handleSingleResult($model);
    }

    public function getSitemapEventCount(): int
    {
        return $this->model
            ->newQuery()
            ->join('event_settings', 'events.id', '=', 'event_settings.event_id')
            ->where('events.'.EventDomainObjectAbstract::STATUS, EventStatus::LIVE->name)
            ->where('event_settings.'.EventSettingDomainObjectAbstract::ALLOW_SEARCH_ENGINE_INDEXING, true)
            ->whereNull('events.'.EventDomainObjectAbstract::DELETED_AT)
            ->count();
    }

    public function getUpcomingEventsWithCompletedOrders(int $accountId): Collection
    {
        return $this->runQuery(function () use ($accountId) {
            $events = $this->model
                ->where('events.'.EventDomainObjectAbstract::ACCOUNT_ID, $accountId)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('event_occurrences')
                        ->whereColumn('event_occurrences.event_id', 'events.id')
                        ->whereNull('event_occurrences.deleted_at')
                        ->whereRaw('COALESCE(event_occurrences.end_date, event_occurrences.start_date) >= ?', [now()]);
                })
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('orders')
                        ->whereColumn('orders.event_id', 'events.id')
                        ->whereNull('orders.deleted_at')
                        ->where('orders.status', OrderStatus::COMPLETED->name);
                })
                ->get();

            return $this->handleResults($events);
        });
    }
}
