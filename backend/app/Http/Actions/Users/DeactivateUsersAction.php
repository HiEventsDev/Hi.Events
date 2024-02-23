<?php

namespace TicketKitten\Http\Actions\Users;

use Illuminate\Http\Response;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\DomainObjects\Status\UserStatus;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;

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
