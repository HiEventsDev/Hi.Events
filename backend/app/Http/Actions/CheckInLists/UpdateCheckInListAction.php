<?php

namespace HiEvents\Http\Actions\CheckInLists;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\CheckInList\UpsertCheckInListRequest;
use HiEvents\Resources\CheckInList\CheckInListResource;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use HiEvents\Services\Handlers\CheckInList\DTO\UpsertCheckInListDTO;
use HiEvents\Services\Handlers\CheckInList\UpdateCheckInlistHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UpdateCheckInListAction extends BaseAction
{
    public function __construct(
        private readonly UpdateCheckInlistHandler $updateCheckInlistHandler,
    )
    {
    }

    public function __invoke(UpsertCheckInListRequest $request, int $eventId, int $checkInListId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $checkInList = $this->updateCheckInlistHandler->handle(
                new UpsertCheckInListDTO(
                    name: $request->validated('name'),
                    description: $request->validated('description'),
                    eventId: $eventId,
                    ticketIds: $request->validated('ticket_ids'),
                    expiresAt: $request->validated('expires_at'),
                    activatesAt: $request->validated('activates_at'),
                    id: $checkInListId,
                )
            );
        } catch (UnrecognizedTicketIdException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->resourceResponse(
            resource: CheckInListResource::class,
            data: $checkInList
        );
    }
}
