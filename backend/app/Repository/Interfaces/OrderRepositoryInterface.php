<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Enterprise\BoxOffice\Repository\DTO\BoxOfficeSalesCountDTO;
use HiEvents\Enterprise\BoxOffice\Repository\DTO\BoxOfficeSummaryRowDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<OrderDomainObject>
 */
interface OrderRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findByBoxOfficeId(int $boxOfficeId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findByOrganizerId(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator;

    public function getOrderItems(int $orderId);

    public function getAttendees(int $orderId);

    public function addOrderItem(array $data): OrderItemDomainObject;

    public function findByShortId(string $orderShortId): ?OrderDomainObject;

    public function findOrdersAssociatedWithProducts(
        int $eventId,
        array $productIds,
        array $orderStatuses,
        ?int $eventOccurrenceId = null,
        ?array $eventOccurrenceIds = null,
    ): Collection;

    public function countOrdersAssociatedWithProducts(
        int $eventId,
        array $productIds,
        array $orderStatuses,
        ?int $eventOccurrenceId = null,
        ?array $eventOccurrenceIds = null,
    ): int;

    public function countActivePromoCodeUsage(int $promoCodeId): int;

    public function getAllOrdersForAdmin(
        ?string $search = null,
        int $perPage = 20,
        ?string $sortBy = 'created_at',
        ?string $sortDirection = 'desc'
    ): LengthAwarePaginator;

    public function hasCompletedPaidOrderForAccount(int $accountId): bool;

    public function accountHasCompletedOrders(int $accountId): bool;

    /**
     * @param  array<int>  $boxOfficeIds
     * @return Collection<BoxOfficeSalesCountDTO>
     */
    public function getBoxOfficeSalesCountsByIds(array $boxOfficeIds): Collection;

    /**
     * @return Collection<BoxOfficeSummaryRowDTO>
     */
    public function getBoxOfficeSummary(int $boxOfficeId, string $timezone, ?string $from, ?string $to): Collection;
}
