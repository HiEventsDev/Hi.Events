<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Attendee\PartialEditAttendeeRequest;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Services\Application\Handlers\Attendee\DTO\PartialEditAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\PartialEditAttendeeHandler;
use Illuminate\Http\JsonResponse;

class PartialEditAttendeeAction extends BaseAction
{
    public function __construct(
        private readonly PartialEditAttendeeHandler $partialEditAttendeeHandler,
    )
    {
    }

    public function __invoke(PartialEditAttendeeRequest $request, int $eventId, int $attendeeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $updatedAttendee = $this->partialEditAttendeeHandler->handle(PartialEditAttendeeDTO::fromArray([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'status' => $request->input('status'),
            'event_id' => $eventId,
            'attendee_id' => $attendeeId,
        ]));

        return $this->resourceResponse(
            resource: AttendeeResource::class,
            data: $updatedAttendee,
        );
    }
}
