<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Memberships;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\MembershipPlanRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetMembershipPlansAction extends BaseAction
{
    public function __construct(
        private readonly MembershipPlanRepositoryInterface $membershipPlanRepository,
    ) {
    }

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        $plans = $this->membershipPlanRepository->findByOrganizerId($organizerId);

        return $this->jsonResponse($plans->map(fn($p) => $p->toArray())->toArray());
    }
}
