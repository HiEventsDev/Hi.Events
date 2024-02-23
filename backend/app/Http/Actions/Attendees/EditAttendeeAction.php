<?php

namespace TicketKitten\Http\Actions\Attendees;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\NoTicketsAvailableException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\EditAttendeeDTO;
use TicketKitten\Http\Request\Attendee\EditAttendeeRequest;
use TicketKitten\Resources\Attendee\AttendeeResource;
use TicketKitten\Service\Handler\Attendee\EditAttendeeHandler;

class EditAttendeeAction extends BaseAction
{
    private EditAttendeeHandler $handler;

    public function __construct(EditAttendeeHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function __invoke(EditAttendeeRequest $request, int $eventId, int $attendeeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $updatedAttendee = $this->handler->handle(EditAttendeeDTO::fromArray([
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'email' => $request->input('email'),
                'ticket_id' => $request->input('ticket_id'),
                'ticket_price_id' => $request->input('ticket_price_id'),
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
            ]));
        } catch (NoTicketsAvailableException $exception) {
            throw ValidationException::withMessages([
                'ticket_id' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: AttendeeResource::class,
            data: $updatedAttendee,
        );
    }
}
