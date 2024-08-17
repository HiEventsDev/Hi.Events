<?php

namespace HiEvents\Http\Actions\CheckInLists;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\CheckInList\UpsertCheckInListRequest;
use HiEvents\Resources\CheckInList\CheckInListResource;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use HiEvents\Services\Handlers\CheckInList\CreateCheckInListHandler;
use HiEvents\Services\Handlers\CheckInList\DTO\UpsertCheckInListDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreateCheckInListAction extends BaseAction
{
    public function __construct(
        private readonly CreateCheckInListHandler $checkInListHandler,
    )
    {
    }

    public function __invoke(UpsertCheckInListRequest $request, int $eventId): JsonResponse
    {
        try {
            $checkInList = $this->checkInListHandler->handle(
                new UpsertCheckInListDTO(
                    name: $request->validated('name'),
                    description: $request->validated('description'),
                    eventId: $eventId,
                    ticketIds: $request->validated('ticket_ids'),
                    expiresAt: $request->validated('expires_at'),
                    activatesAt: $request->validated('activates_at'),
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
