<?php

namespace HiEvents\Http\Actions\CapacityAssignments;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CapacityAssignment\CapacityAssignmentResource;
use HiEvents\Services\Application\Handlers\CapacityAssignment\DTO\GetCapacityAssignmentsDTO;
use HiEvents\Services\Application\Handlers\CapacityAssignment\GetCapacityAssignmentsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCapacityAssignmentsAction extends BaseAction
{
    public function __construct(
        private readonly GetCapacityAssignmentsHandler $getCapacityAssignmentsHandler,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->filterableResourceResponse(
            resource: CapacityAssignmentResource::class,
            data: $this->getCapacityAssignmentsHandler->handle(
                GetCapacityAssignmentsDTO::fromArray([
                    'eventId' => $eventId,
                    'queryParams' => $this->getPaginationQueryParams($request),
                ]),
            ),
            domainObject: CapacityAssignmentDomainObject::class,
        );
    }
}
