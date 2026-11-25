<?php

namespace HiEvents\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\TicketResale\TicketResaleStateService;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAttendeesHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly TicketResaleStateService $ticketResaleStateService,
    ) {}

    public function handle(int $eventId, QueryParamsDTO $queryParams): LengthAwarePaginator
    {
        $attendees = $this->attendeeRepository
            ->loadRelation(new Relationship(
                domainObject: OrderDomainObject::class,
                name: 'order'
            ))
            ->loadRelation(new Relationship(
                domainObject: AttendeeCheckInDomainObject::class,
                name: 'check_ins'
            ))
            ->loadRelation(new Relationship(
                domainObject: EventOccurrenceDomainObject::class,
                name: 'event_occurrence'
            ))
            ->findByEventId($eventId, $queryParams);

        $this->ticketResaleStateService->summarize($attendees->getCollection());

        return $attendees;
    }
}
