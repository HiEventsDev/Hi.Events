<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceStatisticDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetEventOccurrencesHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    )
    {
    }

    public function handle(int $eventId, QueryParamsDTO $queryParams): LengthAwarePaginator
    {
        return $this->occurrenceRepository
            ->loadRelation(EventOccurrenceStatisticDomainObject::class)
            ->findByEventId($eventId, $queryParams);
    }
}
