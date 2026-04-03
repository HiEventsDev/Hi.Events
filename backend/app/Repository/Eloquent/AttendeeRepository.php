<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
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

    public function findByEventIdForExport(int $eventId): Collection
    {
        $this->applyConditions([
            'attendees.event_id' => $eventId,
        ]);

        $this->model->select('attendees.*');
        $this->model->join('orders', 'orders.id', '=', 'attendees.order_id');
        $this->model->whereIn('orders.status', [
            OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            OrderStatus::COMPLETED->name,
            OrderStatus::CANCELLED->name
        ]);

        $model = $this->model->limit(10000)->get();
        $this->resetModel();

        return $this->handleResults($model);
    }


    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            ['attendees.event_id', '=', $eventId]
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(
                        DB::raw(
                            sprintf(
                                "(%s||' '||%s)",
                                'attendees.' . AttendeeDomainObjectAbstract::FIRST_NAME,
                                'attendees.' . AttendeeDomainObjectAbstract::LAST_NAME,
                            )
                        ), 'ilike', '%' . $params->query . '%')
                    ->orWhere('attendees.' . AttendeeDomainObjectAbstract::LAST_NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere('attendees.' . AttendeeDomainObjectAbstract::FIRST_NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere('attendees.' . AttendeeDomainObjectAbstract::PUBLIC_ID, 'ilike', '%' . $params->query . '%')
                    ->orWhere('attendees.' . AttendeeDomainObjectAbstract::EMAIL, 'ilike', '%' . $params->query . '%');
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
            $this->model = $this->model->orderBy('attendees.' . $sortBy, $sortDirection);
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
                                'attendees.' . AttendeeDomainObjectAbstract::FIRST_NAME,
                                'attendees.' . AttendeeDomainObjectAbstract::LAST_NAME,
                            )
                        ), 'ilike', '%' . $params->query . '%')
                    ->orWhere('attendees.' . AttendeeDomainObjectAbstract::LAST_NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere('attendees.' . AttendeeDomainObjectAbstract::FIRST_NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere('attendees.' . AttendeeDomainObjectAbstract::PUBLIC_ID, 'ilike', '%' . $params->query . '%')
                    ->orWhere('attendees.' . AttendeeDomainObjectAbstract::EMAIL, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->select('attendees.*')
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->join('product_check_in_lists', 'product_check_in_lists.product_id', '=', 'attendees.product_id')
            ->join('check_in_lists', 'check_in_lists.id', '=', 'product_check_in_lists.check_in_list_id')
            ->where('check_in_lists.short_id', $shortId)
            ->whereIn('attendees.status',[AttendeeStatus::ACTIVE->name, AttendeeStatus::CANCELLED->name, AttendeeStatus::AWAITING_PAYMENT->name])
            ->whereIn('orders.status', [OrderStatus::COMPLETED->name, OrderStatus::AWAITING_OFFLINE_PAYMENT->name]);

        $this->loadRelation(new Relationship(AttendeeCheckInDomainObject::class, name: 'check_ins'));

        return $this->simplePaginateWhere(
            where: $where,
            limit: min($params->per_page, 250),
        );
    }

    public function getAllAttendeesForAdmin(
        ?string $search = null,
        int $perPage = 20,
        ?string $sortBy = 'created_at',
        ?string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        $this->model = $this->model
            ->select('attendees.*')
            ->join('orders', 'orders.id', '=', 'attendees.order_id')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->join('accounts', 'accounts.id', '=', 'events.account_id');

        if ($search) {
            $this->model = $this->model->where(function ($q) use ($search) {
                $q->where('attendees.email', 'ilike', '%' . $search . '%')
                    ->orWhere('attendees.first_name', 'ilike', '%' . $search . '%')
                    ->orWhere('attendees.last_name', 'ilike', '%' . $search . '%')
                    ->orWhere('attendees.public_id', 'ilike', '%' . $search . '%')
                    ->orWhere('attendees.short_id', 'ilike', '%' . $search . '%');
            });
        }

        $this->model = $this->model
            ->whereIn('orders.status', [
                OrderStatus::COMPLETED->name,
                OrderStatus::CANCELLED->name,
                OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            ]);

        $allowedSortColumns = ['created_at', 'first_name', 'last_name', 'email'];
        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'created_at';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $this->model = $this->model->orderBy('attendees.' . $sortColumn, $sortDir);

        $this->loadRelation(new Relationship(OrderDomainObject::class, nested: [
            new Relationship(EventDomainObject::class, nested: [
                new Relationship(AccountDomainObject::class, name: 'account')
            ], name: 'event')
        ], name: 'order'));
        $this->loadRelation(new Relationship(ProductDomainObject::class, name: 'product'));

        return $this->paginate($perPage);
    }
}
