<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Attendee\EditAttendeeRequest;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Services\Handlers\Attendee\DTO\EditAttendeeDTO;
use HiEvents\Services\Handlers\Attendee\EditAttendeeHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditAttendeeAction extends BaseAction
{
    public function __construct(
        private readonly EditAttendeeHandler $handler)
    {
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
