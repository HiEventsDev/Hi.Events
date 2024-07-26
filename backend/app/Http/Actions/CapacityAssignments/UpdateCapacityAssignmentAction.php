<?php

namespace HiEvents\Http\Actions\CapacityAssignments;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\CapacityAssigment\UpsertCapacityAssignmentRequest;
use HiEvents\Resources\CapacityAssignment\CapacityAssignmentResource;
use HiEvents\Services\Domain\Ticket\Exception\UnrecognizedTicketIdException;
use HiEvents\Services\Handlers\CapacityAssignment\DTO\UpsertCapacityAssignmentDTO;
use HiEvents\Services\Handlers\CapacityAssignment\UpdateCapacityAssignmentHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UpdateCapacityAssignmentAction extends BaseAction
{
    public function __construct(
        private readonly UpdateCapacityAssignmentHandler $updateCapacityAssignmentHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $capacityAssignmentId, UpsertCapacityAssignmentRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $assignment = $this->updateCapacityAssignmentHandler->handle(
                UpsertCapacityAssignmentDTO::fromArray([
                    'id' => $capacityAssignmentId,
                    'name' => $request->validated('name'),
                    'event_id' => $eventId,
                    'capacity' => $request->validated('capacity'),
                    'applies_to' => $request->validated('applies_to'),
                    'status' => $request->validated('status'),
                    'ticket_ids' => $request->validated('ticket_ids'),
                ]),
            );
        } catch (UnrecognizedTicketIdException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->resourceResponse(
            resource: CapacityAssignmentResource::class,
            data: $assignment,
        );
    }
}
