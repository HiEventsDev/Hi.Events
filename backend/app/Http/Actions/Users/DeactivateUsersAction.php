<?php

namespace HiEvents\Http\Actions\Users;

use Illuminate\Http\Response;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;

class DeactivateUsersAction extends BaseAction
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function __invoke(int $userId): Response
    {
        $this->isActionAuthorized($userId, UserDomainObject::class, Role::ADMIN);

        $authUser = $this->getAuthenticatedUser();

        $this->userRepository->updateWhere(
            attributes: [
                'status' => UserStatus::INACTIVE->name,
            ],
            where: [
                'id' => $authUser->getId(),
                'account_id' => $authUser->getAccountId(),
            ]
        );

        return $this->deletedResponse();
    }
}
