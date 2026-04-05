<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Memberships;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\MembershipRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateMembershipAction extends BaseAction
{
    public function __construct(
        private readonly MembershipRepositoryInterface $membershipRepository,
    ) {
    }

    public function __invoke(int $organizerId, int $planId, int $membershipId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:active,expired,cancelled,suspended',
            'expires_at' => 'nullable|date',
            'auto_renew' => 'boolean',
            'notes' => 'nullable|string|max:5000',
        ]);

        $membership = $this->membershipRepository->updateWhere(
            attributes: $validated,
            where: [
                'id' => $membershipId,
                'membership_plan_id' => $planId,
                'account_id' => $this->getAuthenticatedAccountId(),
            ],
        );

        return $this->jsonResponse($membership->toArray());
    }
}
