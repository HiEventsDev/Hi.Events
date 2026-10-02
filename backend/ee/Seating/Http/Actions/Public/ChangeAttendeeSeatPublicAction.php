<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions\Public;

use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Http\Request\MoveAttendeeSeatRequest;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\ChangeAttendeeSeatPublicDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\Public\ChangeAttendeeSeatPublicHandler;
use HiEvents\Exceptions\SelfServiceDisabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Attendee\AttendeeResourcePublic;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class ChangeAttendeeSeatPublicAction extends BaseAction
{
    public function __construct(
        private readonly ChangeAttendeeSeatPublicHandler $handler,
    ) {}

    public function __invoke(int $eventId, string $orderShortId, string $attendeeShortId, MoveAttendeeSeatRequest $request): JsonResponse
    {
        try {
            $attendee = $this->handler->handle(new ChangeAttendeeSeatPublicDTO(
                event_id: $eventId,
                order_short_id: $orderShortId,
                attendee_short_id: $attendeeShortId,
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
        } catch (SelfServiceDisabledException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->getCode());
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_NOT_FOUND);
        }

        return $this->resourceResponse(AttendeeResourcePublic::class, $attendee);
    }
}
