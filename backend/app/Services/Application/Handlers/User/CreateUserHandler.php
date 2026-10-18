<?php

namespace HiEvents\Services\Application\Handlers\User;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\CreateUserDTO;
use HiEvents\Services\Domain\Account\AccountUserAssociationService;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessUpdateService;
use HiEvents\Services\Domain\Auth\Access\AdminAccessNotificationService;
use HiEvents\Services\Domain\Auth\Access\DTO\AccountUserAccessData;
use HiEvents\Services\Domain\Auth\Access\Exception\InvalidAccessGrantException;
use HiEvents\Services\Domain\User\SendUserInvitationService;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class CreateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AccountRepositoryInterface $accountRepository,
        private AccountUserRepositoryInterface $accountUserRepository,
        private SendUserInvitationService $sendUserInvitationService,
        private AccountUserAssociationService $accountUserAssociationService,
        private AccountUserAccessUpdateService $accessUpdateService,
        private AdminAccessNotificationService $adminAccessNotificationService,
        private SsoEnforcementService $ssoEnforcementService,
        private DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws InvalidAccessGrantException
     * @throws Throwable
     * @throws UnauthorizedException
     */
    public function handle(CreateUserDTO $userData): UserDomainObject
    {
        if ($userData->role === Role::SUPERADMIN) {
            throw new UnauthorizedException(
                __('SUPERADMIN users cannot be created through the application')
            );
        }

        return $this->databaseManager->transaction(function () use ($userData) {
            $existingUser = $this->getExistingUser($userData);

            $authenticatedAccount = $this->accountRepository->findById($userData->account_id);

            $invitedUser = $existingUser ?? $this->createUser($userData, $authenticatedAccount);

            $accountUser = $this->accountUserAssociationService->associate(
                user: $invitedUser,
                account: $authenticatedAccount,
                role: $userData->role,
                status: UserStatus::INVITED,
                invitedByUserId: $userData->invited_by,
            );

            $this->accessUpdateService->update(
                accountUser: $accountUser,
                role: $userData->role,
                access: $userData->access ?? new AccountUserAccessData,
            );

            if ($this->ssoEnforcementService->shouldEnforceNewMember($authenticatedAccount->getId(), $invitedUser->getEmail(), isAccountOwner: false)) {
                $this->accountUserRepository->updateWhere(['sso_enforced' => true], ['id' => $accountUser->getId()]);
            }

            $invitedUser->setCurrentAccountUser($this->accountUserRepository->findById($accountUser->getId()));

            $this->sendUserInvitationService->sendInvitation($invitedUser, $authenticatedAccount->getId());

            if ($userData->role === Role::ADMIN) {
                $this->adminAccessNotificationService->notifyOwner(
                    accountId: $authenticatedAccount->getId(),
                    newAdminUserId: $invitedUser->getId(),
                    grantedByUserId: $userData->invited_by,
                );
            }

            return $invitedUser;
        });

    }

    private function createUser(CreateUserDTO $userData, AccountDomainObject $authenticatedAccount): UserDomainObject
    {
        return $this->userRepository
            ->create([
                'first_name' => $userData->first_name,
                'last_name' => $userData->last_name,
                'email' => strtolower($userData->email),
                'password' => null,
                'timezone' => $authenticatedAccount->getTimezone(),
            ]);
    }

    /**
     * @throws ResourceConflictException
     */
    private function getExistingUser(CreateUserDTO $userData): ?UserDomainObject
    {
        $existingUser = $this->userRepository
            ->loadRelation(AccountDomainObject::class)
            ->findFirstWhere([
                'email' => strtolower($userData->email),
            ]);

        if ($existingUser === null) {
            return null;
        }

        if ($existingUser->accounts->some(fn ($account) => $account->getId() === $userData->account_id)) {
            throw new ResourceConflictException(
                __('The email :email already exists on this account', [
                    'email' => $userData->email,
                ])
            );
        }

        return $existingUser;
    }
}
