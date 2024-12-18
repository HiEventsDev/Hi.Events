<?php

namespace HiEvents\Http\Actions\CapacityAssignments;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\CapacityAssigment\UpsertCapacityAssignmentRequest;
use HiEvents\Resources\CapacityAssignment\CapacityAssignmentResource;
use HiEvents\Services\Application\Handlers\CapacityAssignment\DTO\UpsertCapacityAssignmentDTO;
use HiEvents\Services\Application\Handlers\CapacityAssignment\UpdateCapacityAssignmentHandler;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
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
                    'product_ids' => $request->validated('product_ids'),
                ]),
            );
        } catch (UnrecognizedProductIdException $exception) {
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
