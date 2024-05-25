<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\InvalidTicketPriceId;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Attendee\CreateAttendeeRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Services\Handlers\Attendee\CreateAttendeeHandler;
use HiEvents\Services\Handlers\Attendee\DTO\CreateAttendeeDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateAttendeeAction extends BaseAction
{
    private CreateAttendeeHandler $createAttendeeHandler;

    public function __construct(CreateAttendeeHandler $createAttendeeHandler)
    {
        $this->createAttendeeHandler = $createAttendeeHandler;
    }

    /**
     * @throws ValidationException|Throwable
     */
    public function __invoke(CreateAttendeeRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $attendee = $this->createAttendeeHandler->handle(CreateAttendeeDTO::fromArray(
                array_merge($request->validationData(), [
                    'event_id' => $eventId,
                ])
            ));
        } catch (NoTicketsAvailableException $exception) {
            throw ValidationException::withMessages([
                'ticket_id' => $exception->getMessage(),
            ]);
        } catch (InvalidTicketPriceId $exception) {
            throw ValidationException::withMessages([
                'ticket_price_id' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: AttendeeResource::class,
            data: $attendee,
            statusCode: ResponseCodes::HTTP_CREATED
        );
    }
}
