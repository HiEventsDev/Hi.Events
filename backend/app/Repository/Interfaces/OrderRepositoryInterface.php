<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<OrderDomainObject>
 */
interface OrderRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findByOrganizerId(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator;

    public function getOrderItems(int $orderId);

    public function getAttendees(int $orderId);

    public function addOrderItem(array $data): OrderItemDomainObject;

    public function findByShortId(string $orderShortId): ?OrderDomainObject;

    public function findOrdersAssociatedWithProducts(int $eventId, array $productIds, array $orderStatuses): Collection;

    public function countOrdersAssociatedWithProducts(int $eventId, array $productIds, array $orderStatuses): int;

    public function getAllOrdersForAdmin(
        ?string $search = null,
        int $perPage = 20,
        ?string $sortBy = 'created_at',
        ?string $sortDirection = 'desc'
    ): LengthAwarePaginator;

    public function hasCompletedPaidOrderForAccount(int $accountId): bool;
}
