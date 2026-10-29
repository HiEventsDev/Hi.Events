<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Attendee;
use HiEvents\Repository\DTO\BadgeAttendeeQueryDTO;
use HiEvents\Repository\DTO\BadgeStatsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::SEAT_LABEL, 'ilike', '%'.$params->query.'%');
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
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%')
                    ->orWhere('attendees.'.AttendeeDomainObjectAbstract::SEAT_LABEL, 'ilike', '%'.$params->query.'%');
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

    public function findForBadges(BadgeAttendeeQueryDTO $query): LengthAwarePaginator
    {
        $this->model = $this->badgeEligibleQuery($this->model, $query->event_id, $query->include_awaiting_payment)
            ->select('attendees.*');

        if ($query->attendee_ids !== null) {
            $this->model = $this->model->whereIn('attendees.id', $query->attendee_ids);
        }

        if ($query->order_id !== null) {
            $this->model = $this->model->where('attendees.order_id', $query->order_id);
        }

        if ($query->product_ids !== null && $query->product_ids !== []) {
            $this->model = $this->model->whereIn('attendees.product_id', $query->product_ids);
        }

        if ($query->event_occurrence_id !== null) {
            $this->model = $this->model->where('attendees.event_occurrence_id', $query->event_occurrence_id);
        }

        if ($query->only_unprinted) {
            $this->model = $this->model->whereNull('attendees.badge_printed_at');
        }

        $this->model = match ($query->sort_by) {
            BadgeAttendeeQueryDTO::SORT_FIRST_NAME => $this->model
                ->orderByRaw('lower(attendees.first_name) asc nulls last, lower(attendees.last_name) asc nulls last'),
            BadgeAttendeeQueryDTO::SORT_PRODUCT => $this->model
                ->leftJoin('products', 'products.id', '=', 'attendees.product_id')
                ->orderBy('products.title')
                ->orderByRaw('lower(attendees.last_name) asc nulls last, lower(attendees.first_name) asc nulls last'),
            BadgeAttendeeQueryDTO::SORT_CREATED_AT => $this->model,
            default => $this->model
                ->orderByRaw('lower(attendees.last_name) asc nulls last, lower(attendees.first_name) asc nulls last'),
        };

        $this->model = $this->model->orderBy('attendees.id');

        $this->loadRelation(new Relationship(ProductDomainObject::class, name: 'product'));
        $this->loadRelation(new Relationship(EventOccurrenceDomainObject::class, name: 'event_occurrence'));
        $this->setMaxPerPage(BadgeAttendeeQueryDTO::MAX_PER_PAGE);

        return $this->paginateWhere(
            where: [],
            limit: $query->per_page,
            page: $query->page,
        );
    }

    public function getBadgeStats(int $eventId, bool $includeAwaitingPayment, ?int $eventOccurrenceId = null): BadgeStatsDTO
    {
        return $this->runQuery(function () use ($eventId, $includeAwaitingPayment, $eventOccurrenceId) {
            $query = $this->badgeEligibleQuery(Attendee::query(), $eventId, $includeAwaitingPayment);

            if ($eventOccurrenceId !== null) {
                $query->where('attendees.event_occurrence_id', $eventOccurrenceId);
            }

            $row = $query->selectRaw('
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE attendees.badge_printed_at IS NOT NULL) AS printed,
                COUNT(*) FILTER (WHERE attendees.badge_print_count = 0) AS not_printed,
                COUNT(*) FILTER (WHERE attendees.badge_print_count > 0 AND attendees.badge_printed_at IS NULL) AS needs_reprint
            ')->first();

            return new BadgeStatsDTO(
                total: (int) $row->total,
                printed: (int) $row->printed,
                not_printed: (int) $row->not_printed,
                needs_reprint: (int) $row->needs_reprint,
            );
        });
    }

    private function badgeEligibleQuery(Builder|Model $query, int $eventId, bool $includeAwaitingPayment): Builder
    {
        $attendeeStatuses = [AttendeeStatus::ACTIVE->name];
        $orderStatuses = [OrderStatus::COMPLETED->name];

        if ($includeAwaitingPayment) {
            $attendeeStatuses[] = AttendeeStatus::AWAITING_PAYMENT->name;
            $orderStatuses[] = OrderStatus::AWAITING_OFFLINE_PAYMENT->name;
        }

        return $query
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->where('attendees.event_id', $eventId)
            ->whereNull('attendees.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereIn('attendees.status', $attendeeStatuses)
            ->whereIn('orders.status', $orderStatuses);
    }

    public function getSoldQuantitiesByPriceForOccurrence(int $occurrenceId): array
    {
        return $this->runQuery(fn () => $this->soldAttendeesQuery()
            ->where('attendees.event_occurrence_id', $occurrenceId)
            ->groupBy('attendees.product_price_id')
            ->selectRaw('attendees.product_price_id, COUNT(*) AS quantity')
            ->pluck('quantity', 'product_price_id')
            ->map(fn ($quantity) => (int) $quantity)
            ->all());
    }

    public function getMaxSoldPerOccurrenceByPrice(array $productPriceIds): array
    {
        return $this->runQuery(fn () => $this->soldAttendeesQuery()
            ->whereIn('attendees.product_price_id', $productPriceIds)
            ->whereNotNull('attendees.event_occurrence_id')
            ->groupBy('attendees.product_price_id', 'attendees.event_occurrence_id')
            ->selectRaw('attendees.product_price_id, COUNT(*) AS quantity')
            ->get()
            ->groupBy('product_price_id')
            ->map(fn ($rows) => (int) $rows->max('quantity'))
            ->all());
    }

    private function soldAttendeesQuery(): Builder
    {
        return Attendee::query()
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->whereNull('attendees.deleted_at')
            ->whereNull('orders.deleted_at')
            ->where(function (Builder $query) {
                $query
                    ->where('attendees.status', AttendeeStatus::ACTIVE->name)
                    ->orWhere(function (Builder $query) {
                        $query
                            ->where('attendees.status', AttendeeStatus::AWAITING_PAYMENT->name)
                            ->where('orders.status', OrderStatus::AWAITING_OFFLINE_PAYMENT->name);
                    });
            });
    }
}
