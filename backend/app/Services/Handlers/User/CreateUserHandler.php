<?php

namespace HiEvents\Services\Handlers\User;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Account\AccountUserAssociationService;
use HiEvents\Services\Domain\User\SendUserInvitationService;
use HiEvents\Services\Handlers\User\DTO\CreateUserDTO;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class CreateUserHandler
{
    public function __construct(
        private UserRepositoryInterface       $userRepository,
        private AccountRepositoryInterface    $accountRepository,
        private SendUserInvitationService     $sendUserInvitationService,
        private AccountUserAssociationService $accountUserAssociationService,
        private DatabaseManager               $databaseManager,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(CreateUserDTO $userData): UserDomainObject
    {
        return $this->databaseManager->transaction(function () use ($userData) {
            $existingUser = $this->getExistingUser($userData);

            $authenticatedAccount = $this->accountRepository->findById($userData->account_id);

            $invitedUser = $existingUser ?? $this->createUser($userData, $authenticatedAccount);

            $invitedUser->setCurrentAccountUser($this->accountUserAssociationService->associate(
                user: $invitedUser,
                account: $authenticatedAccount,
                role: $userData->role,
                status: UserStatus::INVITED,
                invitedByUserId: $userData->invited_by,
            ));

            $this->sendUserInvitationService->sendInvitation($invitedUser, $authenticatedAccount->getId());

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
                'password' => 'invited', // initially, a user is in an invited state, so they don't have a password
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
                'email' => $userData->email,
            ]);

        if ($existingUser === null) {
            return null;
        }

        if ($existingUser->accounts->some(fn($account) => $account->getId() === $userData->account_id)) {
            throw new ResourceConflictException(
                __('The email :email already exists on this account', [
                    'email' => $userData->email,
                ])
            );
        }

        return $existingUser;
    }
}
