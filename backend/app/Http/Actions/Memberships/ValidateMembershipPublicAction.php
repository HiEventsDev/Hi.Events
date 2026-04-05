<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Memberships;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\MembershipRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidateMembershipPublicAction extends BaseAction
{
    public function __construct(
        private readonly MembershipRepositoryInterface $membershipRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_number' => 'required|string|max:32',
        ]);

        $results = $this->membershipRepository->findWhere([
            'membership_number' => $validated['membership_number'],
        ]);

        if ($results->isEmpty()) {
            return $this->jsonResponse([
                'valid' => false,
                'message' => 'Membership not found.',
            ]);
        }

        $membership = $results->first();

        return $this->jsonResponse([
            'valid' => $membership->isActive(),
            'membership_number' => $membership->getMembershipNumber(),
            'member_name' => $membership->getMemberName(),
            'status' => $membership->getStatus(),
            'expires_at' => $membership->getExpiresAt(),
        ]);
    }
}
