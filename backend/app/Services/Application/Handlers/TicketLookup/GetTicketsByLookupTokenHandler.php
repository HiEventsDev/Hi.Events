<?php

namespace HiEvents\Services\Application\Handlers\TicketLookup;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\InvalidTicketLookupTokenException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\TicketLookup\DTO\LookupTicketDTO;
use HiEvents\Services\Domain\TicketLookup\TicketLookupTokenService;
use Illuminate\Support\Collection;

class GetTicketsByLookupTokenHandler
{
    public function __construct(
        private readonly TicketLookupTokenService $ticketLookupTokenService,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    /**
     * @return Collection<int, LookupTicketDTO>
     *
     * @throws InvalidTicketLookupTokenException
     */
    public function handle(string $token): Collection
    {
        $tickets = $this->attendeeRepository
            ->loadRelation(new Relationship(domainObject: ProductDomainObject::class, name: 'product'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->findGuestTicketsByEmail($this->ticketLookupTokenService->emailFor($token));

        if ($tickets->isEmpty()) {
            return collect();
        }

        $events = $this->eventRepository
            ->findWhereIn(EventDomainObjectAbstract::ID, $tickets->map(fn (AttendeeDomainObject $ticket) => $ticket->getEventId())->unique()->values()->all())
            ->keyBy(fn (EventDomainObject $event) => $event->getId());

        return $tickets
            ->filter(fn (AttendeeDomainObject $ticket) => $events->has($ticket->getEventId()))
            ->map(fn (AttendeeDomainObject $ticket) => new LookupTicketDTO(attendee: $ticket, event: $events->get($ticket->getEventId())))
            ->values();
    }
}
