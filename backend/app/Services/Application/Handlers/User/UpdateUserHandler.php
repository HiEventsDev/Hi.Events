<?php

namespace HiEvents\Services\Application\Handlers\User;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\CannotUpdateResourceException;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateUserDTO;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class UpdateUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface        $userRepository,
        private readonly LoggerInterface                $logger,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly DatabaseManager                $databaseManager,
    )
    {
    }

    /**
     * @throws CannotUpdateResourceException|Throwable
     */
    public function handle(UpdateUserDTO $updateUserData): UserDomainObject
    {
        return $this->databaseManager->transaction(function () use ($updateUserData) {
            return $this->updateUser($updateUserData);
        });
    }

    /**
     * @throws CannotUpdateResourceException
     */
    private function updateUser(UpdateUserDTO $updateUserData): UserDomainObject
    {
        if ($updateUserData->role === Role::SUPERADMIN) {
            throw new CannotUpdateResourceException(__(
                'You cannot assign the Super Admin role to a user'
            ));
        }

        /** @var AccountUserDomainObject $accountUser */
        $accountUser = $this->accountUserRepository->findFirstWhere(
            where: [
                'user_id' => $updateUserData->id,
                'account_id' => $updateUserData->account_id,
            ]
        );

        if ($updateUserData->role !== Role::ADMIN && $accountUser->getIsAccountOwner()) {
            throw new CannotUpdateResourceException(__(
                'You cannot update the role of the account owner'
            ));
        }

        if ($updateUserData->status !== UserStatus::ACTIVE && $accountUser->getIsAccountOwner()) {
            throw new CannotUpdateResourceException(__(
                'You cannot update the status of the account owner'
            ));
        }

        $this->userRepository->updateWhere(
            attributes: [
                'first_name' => $updateUserData->first_name,
                'last_name' => $updateUserData->last_name,
            ],
            where: [
                'id' => $updateUserData->id,
            ]
        );

        $this->accountUserRepository->updateWhere(
            attributes: [
                'role' => $updateUserData->role->name,
                'status' => $updateUserData->status->name,
            ],
            where: [
                'user_id' => $updateUserData->id,
                'account_id' => $updateUserData->account_id,
            ]
        );

        $this->logger->info('User updated', [
            'id' => $updateUserData->id,
            'updated_by_user_id' => $updateUserData->updated_by_user_id,
        ]);

        return $this->userRepository->findByIdAndAccountId(
            userId: $updateUserData->id,
            accountId: $updateUserData->account_id
        );
    }
}
