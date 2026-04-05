<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Memberships;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\MembershipPlanRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateMembershipPlanAction extends BaseAction
{
    public function __construct(
        private readonly MembershipPlanRepositoryInterface $membershipPlanRepository,
    ) {
    }

    public function __invoke(int $organizerId, int $planId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'sometimes|numeric|min:0',
            'billing_interval' => 'sometimes|string|in:monthly,quarterly,yearly,lifetime',
            'benefits' => 'nullable|array',
            'benefits.*' => 'string|max:500',
            'max_events' => 'nullable|integer|min:1',
            'discount_percentage' => 'integer|min:0|max:100',
            'includes_priority_booking' => 'boolean',
            'status' => 'string|in:active,inactive,archived',
        ]);

        $plan = $this->membershipPlanRepository->updateWhere(
            attributes: $validated,
            where: [
                'id' => $planId,
                'organizer_id' => $organizerId,
                'account_id' => $this->getAuthenticatedAccountId(),
            ],
        );

        return $this->jsonResponse($plan->toArray());
    }
}
