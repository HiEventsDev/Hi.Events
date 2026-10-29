<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Http\Request\MoveAttendeeSeatRequest;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\MoveAttendeeSeatDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\MoveAttendeeSeatHandler;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Attendee\AttendeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MoveAttendeeSeatAction extends BaseSeatingAction
{
    public function __construct(
        private readonly MoveAttendeeSeatHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $attendeeId, MoveAttendeeSeatRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $attendee = $this->handler->handle(new MoveAttendeeSeatDTO(
                event_id: $eventId,
                attendee_id: $attendeeId,
                seat_uid: $request->validated('seat_uid'),
                ip_address: $this->getClientIp($request) ?? '',
                user_agent: $request->userAgent(),
            ));
        } catch (SeatsUnavailableException $exception) {
            return $this->errorResponse(
                message: __('That seat has just been taken'),
                statusCode: ResponseCodes::HTTP_CONFLICT,
                errors: ['unavailable_seat_uids' => $exception->getSeatUids()],
            );
        } catch (SeatSelectionInvalidException $exception) {
            throw ValidationException::withMessages(['seat_uid' => $exception->getMessage()]);
        }

        return $this->resourceResponse(AttendeeResource::class, $attendee);
    }
}
