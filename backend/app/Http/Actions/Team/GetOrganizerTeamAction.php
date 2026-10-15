<?php

namespace HiEvents\Http\Actions\Team;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Team\TeamMemberAccessResource;
use HiEvents\Services\Domain\Auth\Access\TeamAccessQueryService;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::MANAGE_TEAM)]
class GetOrganizerTeamAction extends BaseAction
{
    public function __construct(
        private readonly TeamAccessQueryService $teamAccessQueryService,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        return $this->resourceResponse(
            resource: TeamMemberAccessResource::class,
            data: $this->teamAccessQueryService->membersForOrganizer($this->getAuthenticatedAccountId(), $organizerId),
        );
    }
}
