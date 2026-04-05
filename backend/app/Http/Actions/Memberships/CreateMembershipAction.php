<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Memberships;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Repository\Interfaces\MembershipRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateMembershipAction extends BaseAction
{
    public function __construct(
        private readonly MembershipRepositoryInterface $membershipRepository,
    ) {
    }

    public function __invoke(int $organizerId, int $planId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $validated = $request->validate([
            'member_email' => 'required|email|max:255',
            'member_name' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'auto_renew' => 'boolean',
            'notes' => 'nullable|string|max:5000',
        ]);

        $membership = $this->membershipRepository->create([
            'membership_plan_id' => $planId,
            'account_id' => $this->getAuthenticatedAccountId(),
            'member_email' => $validated['member_email'],
            'member_name' => $validated['member_name'],
            'membership_number' => 'MEM-' . strtoupper(Str::random(8)),
            'status' => 'active',
            'starts_at' => $validated['starts_at'],
            'expires_at' => $validated['expires_at'] ?? null,
            'auto_renew' => $validated['auto_renew'] ?? false,
            'events_used' => 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->jsonResponse($membership->toArray(), ResponseCodes::HTTP_CREATED);
    }
}
