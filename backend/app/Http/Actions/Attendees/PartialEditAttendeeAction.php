<?php

namespace HiEvents\Http\Actions\Attendees;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Attendee\PartialEditAttendeeRequest;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Service\Common\Ticket\TicketQuantityService;

class PartialEditAttendeeAction extends BaseAction
{
    private AttendeeRepositoryInterface $attendeeRepository;

    private TicketQuantityService $ticketQuantityService;

    public function __construct(
        AttendeeRepositoryInterface $attendeeRepository,
        TicketQuantityService       $ticketQuantityService
    )
    {
        $this->attendeeRepository = $attendeeRepository;
        $this->ticketQuantityService = $ticketQuantityService;
    }

    /**
     * @todo - Move logic to service
     */
    public function __invoke(PartialEditAttendeeRequest $request, int $eventId, int $attendeeId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $attendee = $this->attendeeRepository->findFirstWhere([
            'id' => $attendeeId,
            'event_id' => $eventId,
        ]);

        if (!$attendee) {
            return $this->notFoundResponse();
        }

        //if status has changed, adjust ticket quantity
        if ($request->has('status') && $request->input('status') !== $attendee->getStatus()) {
            $this->ticketQuantityService->decreaseTicketPriceQuantitySold($attendee->getTicketPriceId());
            $this->ticketQuantityService->increaseTicketPriceQuantitySold($attendee->getTicketPriceId());
        }

        $updatedAttendee = $this->attendeeRepository->updateByIdWhere($attendeeId, [
            'status' => $request->has('status')
                ? strtoupper($request->input('status'))
                : $attendee->getStatus(),
            'first_name' => $request->input('first_name') ?? $attendee->getFirstName(),
            'last_name' => $request->input('last_name') ?? $attendee->getLastName(),
            'email' => $request->input('email') ?? $attendee->getEmail(),
        ], [
            'event_id' => $eventId,
        ]);

        return $this->resourceResponse(
            resource: AttendeeResource::class,
            data: $updatedAttendee,
        );
    }
}
