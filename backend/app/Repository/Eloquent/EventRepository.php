<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Event;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

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
                ])
                ->where(EventDomainObjectAbstract::ORGANIZER_ID, $organizerId)
                ->where(EventDomainObjectAbstract::ACCOUNT_ID, $accountId);
        };

        return $this->findEvents($where, $params);
    }

    public function findEvents(array $where, QueryParamsDTO $params): LengthAwarePaginator
    {
        if (!empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(EventDomainObjectAbstract::TITLE, 'ilike', '%' . $params->query . '%');
            };
        }

        $upcomingEventsFilter = $params->query_params->get('eventsStatus') === 'upcoming';

        if (!empty($params->filter_fields) && !$upcomingEventsFilter) {
            $this->applyFilterFields($params, EventDomainObject::getAllowedFilterFields());
        }

        // Apply custom filter for upcoming events, as it keeps things less complex on the front-end
        if ($upcomingEventsFilter) {
            $where[] = static function (Builder $builder) {
                $builder
                    ->where(EventDomainObjectAbstract::STATUS, '!=', EventStatus::ARCHIVED->getName())
                    ->where(function ($query) {
                        $query->whereNull(EventDomainObjectAbstract::END_DATE)
                            ->orWhere(EventDomainObjectAbstract::END_DATE, '>=', now());
                    });
            };

            $organizerId = $params->filter_fields->first(fn($filter) => $filter->field === EventDomainObjectAbstract::ORGANIZER_ID)?->value;
            if ($organizerId) {
                $this->model = $this->model->where(EventDomainObjectAbstract::ORGANIZER_ID, $organizerId);
            }
        }

        $this->model = $this->model->orderBy(
            $params->sort_by ?? EventDomainObject::getDefaultSort(),
            $params->sort_direction ?? EventDomainObject::getDefaultSortDirection(),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function getUpcomingEventsForAdmin(int $perPage): LengthAwarePaginator
    {
        $now = now();
        $next24Hours = now()->addDay();

        return $this->handleResults($this->model
            ->select('events.*')
            ->with(['account', 'organizer'])
            ->where(EventDomainObjectAbstract::START_DATE, '>=', $now)
            ->where(EventDomainObjectAbstract::START_DATE, '<=', $next24Hours)
            ->whereIn(EventDomainObjectAbstract::STATUS, [
                EventStatus::LIVE->name,
            ])
            ->orderBy(EventDomainObjectAbstract::START_DATE, 'asc')
            ->paginate($perPage));
    }

    public function getAllEventsForAdmin(
        ?string $search = null,
        int $perPage = 20,
        ?string $sortBy = 'start_date',
        ?string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        $this->model = $this->model
            ->select('events.*')
            ->withCount('attendees');

        if ($search) {
            $this->model = $this->model->where(function ($q) use ($search) {
                $q->where(EventDomainObjectAbstract::TITLE, 'ilike', '%' . $search . '%')
                    ->orWhereHas('organizer', function ($orgQuery) use ($search) {
                        $orgQuery->where('name', 'ilike', '%' . $search . '%');
                    });
            });
        }

        $allowedSortColumns = ['start_date', 'end_date', 'title', 'created_at'];
        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'start_date';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $this->model = $this->model->orderBy($sortColumn, $sortDir);

        $this->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'));
        $this->loadRelation(new Relationship(AccountDomainObject::class, name: 'account'));
        $this->loadRelation(new Relationship(EventStatisticDomainObject::class, name: 'event_statistics'));

        return $this->paginate($perPage);
    }

    public function getSitemapEvents(int $page, int $perPage): LengthAwarePaginator
    {
        return $this->handleResults($this->model
            ->select([
                'events.' . EventDomainObjectAbstract::ID,
                'events.' . EventDomainObjectAbstract::TITLE,
                'events.' . EventDomainObjectAbstract::UPDATED_AT,
                'events.' . EventDomainObjectAbstract::START_DATE,
            ])
            ->join('event_settings', 'events.id', '=', 'event_settings.event_id')
            ->where('events.' . EventDomainObjectAbstract::STATUS, EventStatus::LIVE->name)
            ->where('event_settings.' . EventSettingDomainObjectAbstract::ALLOW_SEARCH_ENGINE_INDEXING, true)
            ->whereNull('events.' . EventDomainObjectAbstract::DELETED_AT)
            ->orderBy('events.' . EventDomainObjectAbstract::ID)
            ->paginate($perPage, ['*'], 'page', $page));
    }

    public function getSitemapEventCount(): int
    {
        return $this->model
            ->newQuery()
            ->join('event_settings', 'events.id', '=', 'event_settings.event_id')
            ->where('events.' . EventDomainObjectAbstract::STATUS, EventStatus::LIVE->name)
            ->where('event_settings.' . EventSettingDomainObjectAbstract::ALLOW_SEARCH_ENGINE_INDEXING, true)
            ->whereNull('events.' . EventDomainObjectAbstract::DELETED_AT)
            ->count();
    }
}
