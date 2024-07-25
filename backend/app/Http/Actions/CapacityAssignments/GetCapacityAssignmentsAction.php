<?php

namespace HiEvents\Http\Actions\CapacityAssignments;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CapacityAssignment\CapacityAssignmentResource;
use HiEvents\Services\Handlers\CapacityAssignment\DTO\GetCapacityAssignmentsDTO;
use HiEvents\Services\Handlers\CapacityAssignment\GetCapacityAssignmentsHandler;
use Illuminate\Http\JsonResponse;

class GetCapacityAssignmentsAction extends BaseAction
{
    public function __construct(
        private readonly GetCapacityAssignmentsHandler $getCapacityAssignmentsHandler,
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: CapacityAssignmentResource::class,
            data: $this->getCapacityAssignmentsHandler->handle(
                GetCapacityAssignmentsDTO::fromArray([
                    'eventId' => $eventId,
                ]),
            ),
        );
    }
}
