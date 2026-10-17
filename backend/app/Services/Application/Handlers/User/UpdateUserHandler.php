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
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessUpdateService;
use HiEvents\Services\Domain\Auth\Access\AdminAccessNotificationService;
use HiEvents\Services\Domain\Auth\Access\DTO\AccountUserAccessData;
use HiEvents\Services\Domain\Auth\Access\Exception\InvalidAccessGrantException;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class UpdateUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly AccountUserAccessUpdateService $accessUpdateService,
        private readonly AdminAccessNotificationService $adminAccessNotificationService,
        private readonly DatabaseManager $databaseManager,
        private readonly ApiKeyService $apiKeyService,
    ) {}

    /**
     * @throws CannotUpdateResourceException|InvalidAccessGrantException|Throwable
     */
    public function handle(UpdateUserDTO $updateUserData): UserDomainObject
    {
        return $this->databaseManager->transaction(function () use ($updateUserData) {
            return $this->updateUser($updateUserData);
        });
    }

    /**
     * @throws CannotUpdateResourceException|InvalidAccessGrantException
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

        if ($updateUserData->status !== null && $updateUserData->status !== UserStatus::ACTIVE && $accountUser->getIsAccountOwner()) {
            throw new CannotUpdateResourceException(__(
                'You cannot update the status of the account owner'
            ));
        }

        if ($updateUserData->status !== null && $accountUser->getStatus() === UserStatus::INVITED->name) {
            throw new CannotUpdateResourceException(__(
                'An invitation becomes active only when it is accepted'
            ));
        }

        if ($this->accountUserRepository->findWhere(['user_id' => $updateUserData->id])->count() === 1) {
            $this->userRepository->updateWhere(
                attributes: [
                    'first_name' => $updateUserData->first_name,
                    'last_name' => $updateUserData->last_name,
                ],
                where: [
                    'id' => $updateUserData->id,
                ]
            );
        }

        $this->accountUserRepository->updateWhere(
            attributes: array_filter([
                'role' => $updateUserData->role->name,
                'status' => $updateUserData->status?->name,
            ]),
            where: [
                'user_id' => $updateUserData->id,
                'account_id' => $updateUserData->account_id,
            ]
        );

        if ($updateUserData->status === UserStatus::INACTIVE) {
            $this->apiKeyService->revokeAllForMember($updateUserData->id, $updateUserData->account_id);
        }

        if ($updateUserData->access !== null || ! $updateUserData->role->canBeRestricted()) {
            $this->accessUpdateService->update(
                accountUser: $accountUser,
                role: $updateUserData->role,
                access: $updateUserData->access ?? new AccountUserAccessData,
            );
        }

        if ($updateUserData->role === Role::ADMIN && $accountUser->getRole() !== Role::ADMIN->name) {
            $this->adminAccessNotificationService->notifyOwner(
                accountId: $updateUserData->account_id,
                newAdminUserId: $updateUserData->id,
                grantedByUserId: $updateUserData->updated_by_user_id,
            );
        }

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
