<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
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

    /**
     * @return array<int, int> product_price_id => quantity
     */
    public function getSoldQuantitiesByPriceForOccurrence(int $occurrenceId): array;

    /**
     * @return array<int, int> product_price_id => highest quantity sold on any single occurrence
     */
    public function getMaxSoldPerOccurrenceByPrice(array $productPriceIds): array;
}
