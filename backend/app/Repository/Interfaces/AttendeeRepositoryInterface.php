<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\DTO\BadgeAttendeeQueryDTO;
use HiEvents\Repository\DTO\BadgeStatsDTO;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<AttendeeDomainObject>
 */
interface AttendeeRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findByEventIdForExport(int $eventId, ?int $eventOccurrenceId = null): Collection;

    public function getAttendeesByCheckInShortId(string $shortId, QueryParamsDTO $params): Paginator;

    public function findForBadges(BadgeAttendeeQueryDTO $query): LengthAwarePaginator;

    public function getBadgeStats(int $eventId, bool $includeAwaitingPayment, ?int $eventOccurrenceId = null): BadgeStatsDTO;

    /**
     * @return array<int, int> product_price_id => quantity
     */
    public function getSoldQuantitiesByPriceForOccurrence(int $occurrenceId): array;

    /**
     * @return array<int, int> product_price_id => highest quantity sold on any single occurrence
     */
    public function getMaxSoldPerOccurrenceByPrice(array $productPriceIds): array;
}
