<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Memberships;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\MembershipRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetMembershipsAction extends BaseAction
{
    public function __construct(
        private readonly MembershipRepositoryInterface $membershipRepository,
    ) {
    }

    public function __invoke(int $organizerId, int $planId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $memberships = $this->membershipRepository->findByPlanId($planId);

        return $this->jsonResponse($memberships->map(fn($m) => $m->toArray())->toArray());
    }
}
