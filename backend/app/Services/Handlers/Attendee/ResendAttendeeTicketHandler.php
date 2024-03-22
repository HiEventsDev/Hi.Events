<?php

namespace HiEvents\Services\Handlers\Attendee;

use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Attendee\SendAttendeeTicketService;
use HiEvents\Services\Handlers\Attendee\DTO\ResendAttendeeTicketDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

readonly class ResendAttendeeTicketHandler
{
    public function __construct(
        private SendAttendeeTicketService   $sendAttendeeTicketService,
        private AttendeeRepositoryInterface $attendeeRepository,
        private EventRepositoryInterface    $eventRepository,
        private LoggerInterface             $logger,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function handle(ResendAttendeeTicketDTO $resendAttendeeTicketDTO): void
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            'id' => $resendAttendeeTicketDTO->attendeeId,
            'event_id' => $resendAttendeeTicketDTO->eventId,
        ]);

        if (!$attendee) {
            throw new ResourceNotFoundException();
        }

        if ($attendee->getStatus() !== AttendeeStatus::ACTIVE->name) {
            throw new ResourceConflictException('You cannot resend the ticket of an inactive attendee');
        }

        $event = $this->eventRepository->findById($resendAttendeeTicketDTO->eventId);

        $this->sendAttendeeTicketService->send($attendee, $event);

        $this->logger->info('Attendee ticket resent', [
            'attendeeId' => $resendAttendeeTicketDTO->attendeeId,
            'eventId' => $resendAttendeeTicketDTO->eventId
        ]);
    }
}
