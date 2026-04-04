<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\EventOccurrence;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EventOccurrenceRepository extends BaseRepository implements EventOccurrenceRepositoryInterface
{
    protected function getModel(): string
    {
        return EventOccurrence::class;
    }

    public function getDomainObject(): string
    {
        return EventOccurrenceDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $this->model = $this->model->newQuery()->orderBy(
            column: $this->validateSortColumn($params->sort_by, EventOccurrenceDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, EventOccurrenceDomainObject::class),
        );

        if (!empty($params->filter_fields)) {
            $this->applyFilterFields($params, EventOccurrenceDomainObject::getAllowedFilterFields());

            $timePeriod = $params->filter_fields->firstWhere('field', 'time_period');
            if ($timePeriod) {
                $now = now()->toDateTimeString();
                if ($timePeriod->value === 'upcoming') {
                    $this->model = $this->model->where('start_date', '>=', $now);
                } elseif ($timePeriod->value === 'past') {
                    $this->model = $this->model->where('start_date', '<', $now);
                }
            }
        }

        return $this->paginateWhere(
            where: [
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ],
            limit: $params->per_page,
            page: $params->page,
        );
    }
}
