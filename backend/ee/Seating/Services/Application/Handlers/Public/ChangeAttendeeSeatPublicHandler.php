<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\ChangeAttendeeSeatPublicDTO;
use HiEvents\Enterprise\Seating\Services\Domain\AttendeeSeatMoveService;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\OccurrenceLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Exceptions\SelfServiceDisabledException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\ResendAttendeeTicketDTO;
use HiEvents\Services\Application\Handlers\Attendee\ResendAttendeeTicketHandler;
use HiEvents\Services\Application\Handlers\SelfService\SelfServiceValidationTrait;
use Throwable;

class ChangeAttendeeSeatPublicHandler
{
    use SelfServiceValidationTrait;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventSeatMapLookupService $eventSeatMapLookup,
        private readonly OccurrenceLookupService $occurrenceLookup,
        private readonly AttendeeSeatMoveService $seatMoveService,
        private readonly ResendAttendeeTicketHandler $resendAttendeeTicketHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SeatsUnavailableException
     * @throws SeatSelectionInvalidException
     * @throws SelfServiceDisabledException
     * @throws Throwable
     */
    public function handle(ChangeAttendeeSeatPublicDTO $change): AttendeeDomainObject
    {
        $this->loadAndValidateEvent($change->event_id);

        $order = $this->orderRepository->findByShortId($change->order_short_id);
        $attendee = $order === null || $order->getEventId() !== $change->event_id ? null : $this->attendeeRepository
            ->loadRelation(new Relationship(domainObject: AttendeeCheckInDomainObject::class, name: 'check_ins'))
            ->findFirstWhere([
                AttendeeDomainObjectAbstract::SHORT_ID => $change->attendee_short_id,
                AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
            ]);

        if ($attendee === null) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        $occurrence = $this->occurrenceLookup->getForEvent($change->event_id, $attendee->getEventOccurrenceId());
        $canChange = $this->eventSeatMapLookup->getForEvent($change->event_id)->getAllowSeatChange()
            && $order->isOrderCompleted()
            && $attendee->getStatus() === AttendeeStatus::ACTIVE->name
            && $occurrence->isFuture()
            && ! $occurrence->isCancelled()
            && ($attendee->getCheckIns()?->isEmpty() ?? true);

        if (! $canChange) {
            throw new SeatSelectionInvalidException(__('Seats can no longer be changed for this ticket'));
        }

        $moved = $this->seatMoveService->move($attendee, $change->seat_uid, false, $change->ip_address, $change->user_agent);

        if ($moved->getEmail() !== null) {
            $this->resendAttendeeTicketHandler->handle(new ResendAttendeeTicketDTO(attendeeId: $moved->getId(), eventId: $change->event_id));
        }

        return $moved;
    }
}
