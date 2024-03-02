<?php

namespace HiEvents\Services\Handlers\User;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\CannotUpdateResourceException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Handlers\User\DTO\UpdateUserDTO;
use Psr\Log\LoggerInterface;

class UpdateUserHandler
{
    private UserRepositoryInterface $userRepository;

    private LoggerInterface $logger;

    public function __construct(
        UserRepositoryInterface $userRepository,
        LoggerInterface         $logger
    )
    {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    /**
     * @throws CannotUpdateResourceException
     */
    public function handle(UpdateUserDTO $updateUserData): UserDomainObject
    {
        $user = $this->userRepository->findById($updateUserData->id);

        if ($updateUserData->role !== Role::ADMIN && $user->getIsAccountOwner()) {
            throw new CannotUpdateResourceException(__(
                'You cannot update the role of the account owner'
            ));
        }

        if ($updateUserData->status !== UserStatus::ACTIVE && $user->getIsAccountOwner()) {
            throw new CannotUpdateResourceException(__(
                'You cannot update the status of the account owner'
            ));
        }

        $this->userRepository->updateWhere(
            attributes: [
                'first_name' => $updateUserData->first_name,
                'last_name' => $updateUserData->last_name,
                'role' => $updateUserData->role->name,
                'status' => $updateUserData->status->name,
            ],
            where: [
                'id' => $updateUserData->id,
                'account_id' => $updateUserData->account_id,
            ]
        );

        $this->logger->info('User updated', [
            'id' => $updateUserData->id,
            'updated_by_user_id' => $updateUserData->updated_by_user_id,
        ]);

        return $this->userRepository->findById($updateUserData->id);
    }
}
