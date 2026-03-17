<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Organizer\OrganizerDeletionService;
use Illuminate\Http\JsonResponse;

class GetOrganizerDeletionStatusAction extends BaseAction
{
    public function __construct(
        private readonly OrganizerDeletionService $organizerDeletionService,
    )
    {
    }

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $accountId = $this->getAuthenticatedAccountId();
        $canDelete = $this->organizerDeletionService->canDeleteOrganizer($organizerId, $accountId);
        $reason = $canDelete ? null : $this->organizerDeletionService->getCannotDeleteReason($organizerId, $accountId);

        return $this->jsonResponse([
            'data' => [
                'can_delete' => $canDelete,
                'reason' => $reason,
            ],
        ]);
    }
}
