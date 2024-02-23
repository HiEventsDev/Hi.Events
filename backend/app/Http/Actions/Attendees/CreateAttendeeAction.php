<?php

namespace TicketKitten\Http\Actions\Attendees;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\InvalidTicketPriceId;
use TicketKitten\Exceptions\NoTicketsAvailableException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CreateAttendeeDTO;
use TicketKitten\Http\Request\Attendee\CreateAttendeeRequest;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Resources\Attendee\AttendeeResource;
use TicketKitten\Service\Handler\Attendee\CreateAttendeeHandler;

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
