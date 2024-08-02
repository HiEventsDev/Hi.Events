<?php

namespace HiEvents\Http\Actions\CapacityAssignments;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\CapacityAssignment\DeleteCapacityAssignmentHandler;
use Illuminate\Http\Response;

class DeleteCapacityAssignmentAction extends BaseAction
{
    public function __construct(
        private readonly DeleteCapacityAssignmentHandler $deleteCapacityAssignmentHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $capacityAssignmentId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->deleteCapacityAssignmentHandler->handle(
            $capacityAssignmentId,
            $eventId,
        );

        return $this->noContentResponse();
    }
}
