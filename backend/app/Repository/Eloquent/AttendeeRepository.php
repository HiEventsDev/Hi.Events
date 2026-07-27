<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Attendee;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<AttendeeDomainObject>
 */
class AttendeeRepository extends BaseRepository implements AttendeeRepositoryInterface
{
    protected function getModel(): string
    {
        return Attendee::class;
    }

    public function getDomainObject(): string
    {
        return AttendeeDomainObject::class;
    }

    public function findByEventIdForExport(int $eventId, ?int $eventOccurrenceId = null): Collection
    {
        return $this->runQuery(function () use ($eventId, $eventOccurrenceId) {
            $conditions = [
                'attendees.event_id' => $eventId,
            ];

            if ($eventOccurrenceId !== null) {
                $conditions['attendees.event_occurrence_id'] = $eventOccurrenceId;
            }

            $this->applyConditions($conditions);

            $this->model->select('attendees.*');
            $this->model->join('orders', 'orders.id', '=', 'attendees.order_id');
            $this->model->whereIn('orders.status', [
                OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
                OrderStatus::COMPLETED->name,
                OrderStatus::CANCELLED->name,
            ]);

            $model = $this->model->limit(10000)->get();

            return $this->handleResults($model);
        });
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            ['attendees.event_id', '=', $eventId],
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(
                        DB::raw(
                            sprintf(
                                "(%s||' '||%s)",
                                'attendees.'.AttendeeDomainObjectAbstract::FIRST_NAME,
                                'attendees.'.AttendeeDomainObjectAbstract::LAST_NAME,
                            )
                        ), 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::FIRST_NAME, 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::PUBLIC_ID, 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%');
            };
        }

        $this->model = $this->model->select('attendees.*')
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->whereIn('orders.status', [OrderStatus::COMPLETED->name, OrderStatus::CANCELLED->name, OrderStatus::AWAITING_OFFLINE_PAYMENT->name]);

        if ($params->filter_fields && $params->filter_fields->isNotEmpty()) {
            $this->applyFilterFields($params, AttendeeDomainObject::getAllowedFilterFields(), prefix: 'attendees');
        }

        $sortBy = $this->validateSortColumn($params->sort_by, AttendeeDomainObject::class);
        $sortDirection = $this->validateSortDirection($params->sort_direction, AttendeeDomainObject::class);

        if ($sortBy === AttendeeDomainObject::TICKET_NAME_SORT_KEY) {
            $this->model = $this->model
                ->leftJoin('products', 'products.id', '=', 'attendees.product_id')
                ->orderBy('products.title', $sortDirection);
        } else {
            $this->model = $this->model->orderBy('attendees.'.$sortBy, $sortDirection);
        }

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function getAttendeesByCheckInShortId(string $shortId, QueryParamsDTO $params): Paginator
    {
        $where = [];
        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(
                        DB::raw(
                            sprintf(
                                "(%s||' '||%s)",
                                'attendees.'.AttendeeDomainObjectAbstract::FIRST_NAME,
                                'attendees.'.AttendeeDomainObjectAbstract::LAST_NAME,
                            )
                        ), 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::FIRST_NAME, 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::PUBLIC_ID, 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%');
            };
        }

        $this->model = $this->model->select('attendees.*')
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->join('check_in_lists', function ($join) use ($shortId) {
                $join->on('check_in_lists.event_id', '=', 'attendees.event_id')
                    ->where('check_in_lists.short_id', '=', $shortId)
                    ->whereNull('check_in_lists.deleted_at');
            })
            ->where(function ($query) {
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('product_check_in_lists as pcil')
                        ->whereColumn('pcil.check_in_list_id', 'check_in_lists.id')
                        ->whereColumn('pcil.product_id', 'attendees.product_id')
                        ->whereNull('pcil.deleted_at');
                })->orWhereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('product_check_in_lists as pcil')
                        ->whereColumn('pcil.check_in_list_id', 'check_in_lists.id')
                        ->whereNull('pcil.deleted_at');
                });
            })
            ->whereIn('attendees.status', [AttendeeStatus::ACTIVE->name, AttendeeStatus::CANCELLED->name, AttendeeStatus::AWAITING_PAYMENT->name])
            ->whereIn('orders.status', [OrderStatus::COMPLETED->name, OrderStatus::AWAITING_OFFLINE_PAYMENT->name]);

        $occurrenceFilter = $params->filter_fields?->firstWhere('field', 'event_occurrence_id');
        if ($occurrenceFilter) {
            $this->model = $this->model->where(
                'attendees.event_occurrence_id',
                $occurrenceFilter->value
            );
        }

        $this->loadRelation(new Relationship(AttendeeCheckInDomainObject::class, name: 'check_ins'));
        $this->loadRelation(new Relationship(EventOccurrenceDomainObject::class, name: 'event_occurrence'));

        return $this->simplePaginateWhere(
            where: $where,
            limit: min($params->per_page, 250),
        );
    }
}
