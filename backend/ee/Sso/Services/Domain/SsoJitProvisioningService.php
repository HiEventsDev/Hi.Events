<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Account\AccountUserAssociationService;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessUpdateService;
use HiEvents\Services\Domain\Auth\Access\AdminAccessNotificationService;
use HiEvents\Services\Domain\Auth\Access\DTO\AccountUserAccessData;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use Psr\Log\LoggerInterface;

class SsoJitProvisioningService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly AccountUserAssociationService $accountUserAssociationService,
        private readonly AccountUserAccessUpdateService $accessUpdateService,
        private readonly AdminAccessNotificationService $adminAccessNotificationService,
        private readonly SsoEnforcementService $enforcementService,
        private readonly LoggerInterface $logger,
    ) {}

    public function createUser(AccountSsoConnectionDomainObject $connection, SsoIdentityClaims $claims): UserDomainObject
    {
        $email = (string) $claims->email;
        $account = $this->accountRepository->findById($connection->getAccountId());

        return $this->userRepository->create([
            'email' => $email,
            'first_name' => $claims->firstName ?? strstr($email, '@', true),
            'last_name' => $claims->lastName,
            'password' => null,
            'timezone' => $account->getTimezone() ?? 'UTC',
            'email_verified_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @throws SsoException
     */
    public function ensureMembership(AccountSsoConnectionDomainObject $connection, UserDomainObject $user, bool $userEmailOnVerifiedDomain): void
    {
        $membership = ['user_id' => $user->getId(), 'account_id' => $connection->getAccountId()];

        /** @var AccountUserDomainObject|null $accountUser */
        $accountUser = $this->accountUserRepository->findFirstWhere($membership)
            ?? $this->accountUserRepository->includeDeleted()->findFirstWhere($membership);

        if ($accountUser !== null) {
            $this->assertMembershipUsable($connection, $accountUser, $userEmailOnVerifiedDomain);

            return;
        }

        if (! $userEmailOnVerifiedDomain || ! $connection->getJitProvisioningEnabled()) {
            throw $this->noAccess();
        }

        $this->provision($connection, $user);
    }

    /**
     * @throws SsoException
     */
    private function assertMembershipUsable(AccountSsoConnectionDomainObject $connection, AccountUserDomainObject $accountUser, bool $userEmailOnVerifiedDomain): void
    {
        if ($accountUser->getDeletedAt() !== null || $accountUser->getStatus() === UserStatus::INACTIVE->name) {
            throw $this->noAccess();
        }

        if ($accountUser->getStatus() === UserStatus::ACTIVE->name) {
            return;
        }

        if (! $userEmailOnVerifiedDomain) {
            throw new SsoException(
                SsoErrorCode::INVITATION_PENDING,
                __('You have been invited to join a team. Open the invitation email to finish joining.'),
            );
        }

        $this->accountUserRepository->updateWhere(['status' => UserStatus::ACTIVE->name], ['id' => $accountUser->getId()]);
        $this->userRepository->updateWhere(
            attributes: ['email_verified_at' => now()->toDateTimeString()],
            where: ['id' => $accountUser->getUserId(), ['email_verified_at', 'null', null]],
        );

        $this->logger->info('SSO accepted a pending invitation', [
            'account_id' => $connection->getAccountId(),
            'account_user_id' => $accountUser->getId(),
        ]);
    }

    private function provision(AccountSsoConnectionDomainObject $connection, UserDomainObject $user): void
    {
        $role = Role::tryFrom($connection->getJitDefaultRole()) ?? Role::ORGANIZER;

        if (! in_array($role->value, Role::getAssignableRoles(), true)) {
            $role = Role::ORGANIZER;
        }

        $accountUser = $this->accountUserAssociationService->associate(
            user: $user,
            account: $this->accountRepository->findById($connection->getAccountId()),
            role: $role,
            status: UserStatus::ACTIVE,
        );

        $this->accessUpdateService->update($accountUser, $role, new AccountUserAccessData);

        if ($this->enforcementService->shouldEnforceNewMember($connection->getAccountId(), $user->getEmail(), isAccountOwner: false)) {
            $this->accountUserRepository->updateWhere(['sso_enforced' => true], ['id' => $accountUser->getId()]);
        }

        $this->logger->info('SSO provisioned a team member', [
            'account_id' => $connection->getAccountId(),
            'account_user_id' => $accountUser->getId(),
            'role' => $role->value,
        ]);

        if ($role === Role::ADMIN) {
            $this->adminAccessNotificationService->notifyOwner(
                accountId: $connection->getAccountId(),
                newAdminUserId: $user->getId(),
                grantedByUserId: null,
            );
        }
    }

    private function noAccess(): SsoException
    {
        return new SsoException(
            SsoErrorCode::NO_ACTIVE_ACCOUNT,
            __('You don\'t have access to this account yet. Ask your administrator to invite you.'),
        );
    }
}
