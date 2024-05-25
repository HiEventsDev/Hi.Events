<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Users;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;

class GetUsersAction extends BaseAction
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        return $this->resourceResponse(
            UserResource::class,
            $this->userRepository
                ->loadRelation(new Relationship(domainObject: AccountUserDomainObject::class, name: 'currentAccountUser'))
                ->findUsersByAccountId($this->getAuthenticatedAccountId()),
        );
    }
}
