<?php

namespace HiEvents\Http\Actions\CapacityAssignments;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CapacityAssignment\CapacityAssignmentResource;
use HiEvents\Services\Application\Handlers\CapacityAssignment\GetCapacityAssignmentHandler;
use Illuminate\Http\JsonResponse;

class GetCapacityAssignmentAction extends BaseAction
{
    public function __construct(
        private readonly GetCapacityAssignmentHandler $getCapacityAssignmentsHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $capacityAssignmentId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: CapacityAssignmentResource::class,
            data: $this->getCapacityAssignmentsHandler->handle(
                capacityAssignmentId: $capacityAssignmentId,
                eventId: $eventId,
            ),
        );
    }
}
