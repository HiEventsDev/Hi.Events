<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\MoveAttendeeSeatDTO;
use HiEvents\Enterprise\Seating\Services\Domain\AttendeeSeatMoveService;
use HiEvents\Enterprise\Seating\Services\Domain\OccurrenceLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\ResendAttendeeTicketDTO;
use HiEvents\Services\Application\Handlers\Attendee\ResendAttendeeTicketHandler;
use Throwable;

class MoveAttendeeSeatHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly AttendeeSeatMoveService $seatMoveService,
        private readonly ResendAttendeeTicketHandler $resendAttendeeTicketHandler,
        private readonly OccurrenceLookupService $occurrenceLookup,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SeatsUnavailableException
     * @throws SeatSelectionInvalidException
     * @throws Throwable
     */
    public function handle(MoveAttendeeSeatDTO $move): AttendeeDomainObject
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::ID => $move->attendee_id,
            AttendeeDomainObjectAbstract::EVENT_ID => $move->event_id,
        ]) ?? throw new ResourceNotFoundException(__('Attendee not found'));

        $isMovable = $attendee->getStatus() === AttendeeStatus::ACTIVE->name
            && ! $this->occurrenceLookup->getForEvent($move->event_id, $attendee->getEventOccurrenceId())->isCancelled();

        if (! $isMovable) {
            throw new SeatSelectionInvalidException(__('Only active attendees on a date that is not cancelled can be moved'));
        }

        $moved = $this->seatMoveService->move($attendee, $move->seat_uid, true, $move->ip_address, $move->user_agent);

        if ($moved->getEmail() !== null) {
            $this->resendAttendeeTicketHandler->handle(new ResendAttendeeTicketDTO(attendeeId: $moved->getId(), eventId: $move->event_id));
        }

        return $moved;
    }
}
