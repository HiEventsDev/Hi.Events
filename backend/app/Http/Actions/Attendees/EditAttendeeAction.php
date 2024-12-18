<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Attendee\EditAttendeeRequest;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Services\Application\Handlers\Attendee\DTO\EditAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\EditAttendeeHandler;
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
                'product_id' => $request->input('product_id'),
                'product_price_id' => $request->input('product_price_id'),
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'notes' => $request->input('notes'),
            ]));
        } catch (NoTicketsAvailableException $exception) {
            throw ValidationException::withMessages([
                'product_id' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: AttendeeResource::class,
            data: $updatedAttendee,
        );
    }
}
