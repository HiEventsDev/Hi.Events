<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Memberships;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Repository\Interfaces\MembershipPlanRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreateMembershipPlanAction extends BaseAction
{
    public function __construct(
        private readonly MembershipPlanRepositoryInterface $membershipPlanRepository,
    ) {
    }

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_interval' => 'required|string|in:monthly,quarterly,yearly,lifetime',
            'benefits' => 'nullable|array',
            'benefits.*' => 'string|max:500',
            'max_events' => 'nullable|integer|min:1',
            'discount_percentage' => 'integer|min:0|max:100',
            'includes_priority_booking' => 'boolean',
            'status' => 'string|in:active,inactive',
        ]);

        $plan = $this->membershipPlanRepository->create([
            'account_id' => $this->getAuthenticatedAccountId(),
            'organizer_id' => $organizerId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'currency' => strtoupper($validated['currency']),
            'billing_interval' => $validated['billing_interval'],
            'benefits' => $validated['benefits'] ?? null,
            'max_events' => $validated['max_events'] ?? null,
            'discount_percentage' => $validated['discount_percentage'] ?? 0,
            'includes_priority_booking' => $validated['includes_priority_booking'] ?? false,
            'status' => $validated['status'] ?? 'active',
        ]);

        return $this->jsonResponse($plan->toArray(), ResponseCodes::HTTP_CREATED);
    }
}
