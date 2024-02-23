<?php

namespace TicketKitten\Http\Actions\Attendees;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\CannotCheckInException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CheckInAttendeeDTO;
use TicketKitten\Http\Request\Attendee\CheckInAttendeeRequest;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Resources\Attendee\AttendeeResource;
use TicketKitten\Service\Handler\Attendee\CheckInAttendeeHandler;

class CheckInAttendeeAction extends BaseAction
{
    public function __construct(
        private readonly CheckInAttendeeHandler $checkInAttendeeHandler
    )
    {
    }

    public function __invoke(CheckInAttendeeRequest $request, int $eventId, string $attendeePublicId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $user = $this->getAuthenticatedUser();

        try {
            $attendee = $this->checkInAttendeeHandler->handle(CheckInAttendeeDTO::fromArray([
                'event_id' => $eventId,
                'attendee_public_id' => $attendeePublicId,
                'checked_in_by_user_id' => $user->getId(),
                'action' => $request->validated('action'),
            ]));
        } catch (CannotCheckInException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(AttendeeResource::class, $attendee);
    }
}
