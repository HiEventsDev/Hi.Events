<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Services\Domain\TicketResale\TicketResaleStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetAttendeeAction extends BaseAction
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly TicketResaleStateService $ticketResaleStateService,
    ) {}

    public function __invoke(int $eventId, int $attendeeId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $attendee = $this->attendeeRepository
            ->loadRelation(relationship: QuestionAndAnswerViewDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: ProductDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: ProductPriceDomainObject::class,
                    ),
                ], name: 'product'))
            ->loadRelation(new Relationship(
                domainObject: AttendeeCheckInDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: CheckInListDomainObject::class,
                        name: 'check_in_list',
                    ),
                ],
                name: 'check_ins'
            ))
            ->loadRelation(new Relationship(
                domainObject: EventOccurrenceDomainObject::class,
                nested: [
                    new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                        new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                    ], name: 'event_location'),
                ],
                name: 'event_occurrence',
            ))
            ->findFirstWhere([
                'id' => $attendeeId,
                'event_id' => $eventId,
            ]);

        if (! $attendee) {
            return $this->notFoundResponse();
        }

        $this->ticketResaleStateService->summarize(collect([$attendee]));

        return $this->resourceResponse(AttendeeResource::class, $attendee);
    }
}
