<?php

namespace HiEvents\Services\Domain\TicketTransfer;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketTransferDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;
use HiEvents\Services\Domain\TicketTransfer\DTO\TicketTransferContextDTO;

class TicketTransferContextService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly TicketTransferRepositoryInterface $ticketTransferRepository,
        private readonly TicketResaleListingRepositoryInterface $ticketResaleListingRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function forTicketLink(int $eventId, string $attendeeShortId, ?string $orderShortId = null): TicketTransferContextDTO
    {
        $attendee = $this->withRelations()->findFirstWhere([
            AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId,
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($attendee === null || ($orderShortId !== null && $attendee->getOrder()?->getShortId() !== $orderShortId)) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        return $this->contextFor($attendee);
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function forAttendeeId(int $attendeeId): TicketTransferContextDTO
    {
        $attendee = $this->withRelations()->findFirstWhere([AttendeeDomainObjectAbstract::ID => $attendeeId]);

        if ($attendee === null) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        return $this->contextFor($attendee);
    }

    private function withRelations(): AttendeeRepositoryInterface
    {
        return $this->attendeeRepository
            ->loadRelation(new Relationship(domainObject: OrderDomainObject::class, nested: [
                new Relationship(domainObject: OrderItemDomainObject::class),
            ], name: 'order'))
            ->loadRelation(new Relationship(domainObject: ProductDomainObject::class, name: 'product'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->loadRelation(new Relationship(domainObject: AttendeeCheckInDomainObject::class, name: 'check_ins'));
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function contextFor(AttendeeDomainObject $attendee): TicketTransferContextDTO
    {
        $event = $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(new Relationship(domainObject: OrganizerDomainObject::class, name: 'organizer'))
            ->findById($attendee->getEventId());

        if ($attendee->getOrder() === null || $attendee->getProduct() === null || $attendee->getEventOccurrence() === null) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        return new TicketTransferContextDTO(
            event: $event,
            order: $attendee->getOrder(),
            attendee: $attendee,
            product: $attendee->getProduct(),
            occurrence: $attendee->getEventOccurrence(),
            transfers: $this->ticketTransferRepository->findWhere([
                TicketTransferDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
            ]),
            is_checked_in: $attendee->isCheckedIn(),
            has_open_listing: $this->ticketResaleListingRepository->countWhere([
                TicketResaleListingDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
                TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
            ]) > 0,
        );
    }
}
