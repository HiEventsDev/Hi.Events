<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\Attendee\DTO\ResendAttendeeTicketDTO;
use HiEvents\Services\Handlers\Attendee\ResendAttendeeTicketHandler;
use Illuminate\Http\Response;

class ResendAttendeeTicketAction extends BaseAction
{
    public function __construct(
        private readonly ResendAttendeeTicketHandler $handler
    )
    {
    }

    public function __invoke(int $eventId, int $attendeeId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->handler->handle(new ResendAttendeeTicketDTO(
            attendeeId: $attendeeId,
            eventId: $eventId
        ));

        return $this->noContentResponse();
    }
}
