<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use Illuminate\Support\Collection;

class ResaleOccurrenceAvailabilityService
{
    public function __construct(
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
    ) {}

    /**
     * @param  Collection<int, EventOccurrenceDomainObject>  $occurrences
     * @return Collection<int, EventOccurrenceDomainObject>
     */
    public function markOccurrencesWithListedTickets(int $eventId, Collection $occurrences): Collection
    {
        if ($occurrences->doesntContain(fn (EventOccurrenceDomainObject $occurrence) => $occurrence->isSoldOut())) {
            return $occurrences;
        }

        $occurrenceIds = $this->listingRepository->occurrenceIdsWithAvailableListings($eventId);

        return $occurrences->each(fn (EventOccurrenceDomainObject $occurrence) => $occurrence->setHasListedTickets(
            in_array($occurrence->getId(), $occurrenceIds, true)
        ));
    }
}
