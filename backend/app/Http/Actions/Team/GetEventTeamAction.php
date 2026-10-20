<?php

namespace HiEvents\Http\Actions\Team;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Resources\Team\TeamMemberAccessResource;
use HiEvents\Services\Domain\Auth\Access\TeamAccessQueryService;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::MANAGE_TEAM)]
class GetEventTeamAction extends BaseAction
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly TeamAccessQueryService $teamAccessQueryService,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: TeamMemberAccessResource::class,
            data: $this->teamAccessQueryService->membersForEvent($this->eventRepository->findById($eventId)),
        );
    }
}
