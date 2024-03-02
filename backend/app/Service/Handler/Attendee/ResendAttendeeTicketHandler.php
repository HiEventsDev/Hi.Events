<?php

namespace HiEvents\Service\Handler\Attendee;

use HiEvents\Http\DataTransferObjects\ResendAttendeeTicketDTO;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Service\Common\Attendee\SendAttendeeTicketService;
use Psr\Log\LoggerInterface;

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

    public function handle(ResendAttendeeTicketDTO $resendAttendeeTicketDTO): void
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            'id' => $resendAttendeeTicketDTO->attendeeId,
            'event_id' => $resendAttendeeTicketDTO->eventId,
        ]);

        $event = $this->eventRepository->findById($resendAttendeeTicketDTO->eventId);

        $this->sendAttendeeTicketService->send($attendee, $event);

        $this->logger->info('Attendee ticket resent', [
            'attendeeId' => $resendAttendeeTicketDTO->attendeeId,
            'eventId' => $resendAttendeeTicketDTO->eventId
        ]);
    }
}
