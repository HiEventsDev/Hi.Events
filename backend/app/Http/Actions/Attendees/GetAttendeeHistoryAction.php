<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\OrderAuditLog\TicketHistoryEntryResource;
use HiEvents\Services\Application\Handlers\OrderAuditLog\DTO\GetTicketHistoryDTO;
use HiEvents\Services\Application\Handlers\OrderAuditLog\GetTicketHistoryHandler;
use Illuminate\Http\JsonResponse;

class GetAttendeeHistoryAction extends BaseAction
{
    public function __construct(
        private readonly GetTicketHistoryHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(int $eventId, int $attendeeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: TicketHistoryEntryResource::class,
            data: $this->handler->handle(new GetTicketHistoryDTO(event_id: $eventId, attendee_id: $attendeeId)),
        );
    }
}
