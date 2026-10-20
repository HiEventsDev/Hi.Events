<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::MANAGE_TEAM)]
class GetUsersAction extends BaseAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserAccessService $accessService,
        private readonly SsoEnforcementService $ssoEnforcementService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $users = $this->userRepository
            ->loadRelation(new Relationship(domainObject: AccountUserDomainObject::class, name: 'currentAccountUser'))
            ->findUsersByAccountId($this->getAuthenticatedAccountId());

        $this->accessService->preload($users->map(fn (UserDomainObject $user) => $user->getCurrentAccountUser()));
        $this->ssoEnforcementService->annotateSignInRequirements($this->getAuthenticatedAccountId(), $users);

        return $this->resourceResponse(UserResource::class, $users);
    }
}
