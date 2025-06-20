<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Event;
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
}
