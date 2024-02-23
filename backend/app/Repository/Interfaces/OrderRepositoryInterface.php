<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<OrderDomainObject>
 */
interface OrderRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function getOrderItems(int $orderId);

    public function getAttendees(int $orderId);

    public function addOrderItem(array $data): OrderItemDomainObject;

    public function findByShortId(string $orderShortId): ?OrderDomainObject;
}
