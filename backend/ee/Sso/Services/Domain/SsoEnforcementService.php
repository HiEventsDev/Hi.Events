<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\AccountSsoConnectionType;
use HiEvents\DomainObjects\Enums\AuthMethod;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Enums\SsoSignInRequirement;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Exceptions\SsoRequiredException;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\DTO\SsoEnforcementMember;
use HiEvents\Enterprise\Sso\Services\Domain\DTO\SsoEnforcementPreview;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Illuminate\Support\Collection;

class SsoEnforcementService
{
    public function __construct(
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountSsoDomainService $domainService,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly UserSessionRevocationService $sessionRevocationService,
        private readonly SsoBreakGlassService $breakGlassService,
        private readonly UserIdentityRepositoryInterface $userIdentityRepository,
    ) {}

    public function findConnection(int $accountId): ?AccountSsoConnectionDomainObject
    {
        return $this->connectionRepository->findFirstWhere(['account_id' => $accountId]);
    }

    /**
     * @throws SsoRequiredException
     */
    public function assertSignInAllowed(UserDomainObject $user, int $accountId, AuthContext $context): void
    {
        $connection = $this->findConnection($accountId);

        if ($connection === null || ! $connection->isEnforcing() || $context->ssoConnectionId === $connection->getId()) {
            return;
        }

        $accountUser = $this->accountUserRepository->findFirstWhere(['user_id' => $user->getId(), 'account_id' => $accountId]);

        if ($accountUser === null) {
            return;
        }

        if ($accountUser->getIsAccountOwner()) {
            if (! $context->secondFactorVerified) {
                throw new SsoRequiredException($user->getEmail());
            }

            $this->breakGlassService->record($user, $accountId);

            return;
        }

        if ($this->memberRequiresSso($accountUser, $user->getEmail())) {
            throw new SsoRequiredException($user->getEmail());
        }
    }

    /**
     * @throws SsoRequiredException
     */
    public function assertMemberSignInAllowed(UserDomainObject $user, int $accountId, AuthContext $context): void
    {
        if (! $this->isTokenAllowed($user->getId(), $user->getEmail(), $accountId, $context->method->value, $context->ssoConnectionId)) {
            throw new SsoRequiredException($user->getEmail());
        }
    }

    public function requiresSsoToJoin(int $userId, string $email, int $accountId): bool
    {
        return ! $this->isTokenAllowed($userId, $email, $accountId, AuthMethod::PASSWORD->value, null);
    }

    /**
     * @throws SsoRequiredException
     */
    public function assertSignupAllowed(string $email): void
    {
        $domain = $this->domainService->findUsableDomainForEmail($email);

        if ($domain !== null && $this->findConnection($domain->getAccountId())?->isEnforcing() === true) {
            throw new SsoRequiredException($email);
        }
    }

    public function isConnectionSessionUsable(int $accountId, ?int $ssoConnectionId): bool
    {
        if ($ssoConnectionId === null) {
            return true;
        }

        $connection = $this->findConnection($accountId);

        return $connection !== null && $connection->getId() === $ssoConnectionId && $connection->isActive();
    }

    public function isTokenAllowed(int $userId, string $email, int $accountId, ?string $authMethod, ?int $ssoConnectionId): bool
    {
        if ($authMethod === AuthMethod::IMPERSONATION->value) {
            return true;
        }

        $accountUser = $this->accountUserRepository->findFirstWhere([
            'user_id' => $userId,
            'account_id' => $accountId,
            'sso_enforced' => true,
        ]);

        if ($accountUser === null) {
            return true;
        }

        $connection = $this->findConnection($accountId);

        if ($connection === null || ! $connection->isEnforcing() || $connection->getId() === $ssoConnectionId) {
            return true;
        }

        return ! $this->memberRequiresSso($accountUser, $email);
    }

    public function requiresFreshSecondFactor(int $userId, AuthContext $context): bool
    {
        if ($context->ssoConnectionId !== null) {
            return false;
        }

        return $this->ownedEnforcingAccountUsers($userId)->isNotEmpty();
    }

    /**
     * @return Collection<int, string>
     */
    public function namesOfOwnedEnforcingAccounts(int $userId): Collection
    {
        return $this->ownedEnforcingAccountUsers($userId)
            ->map(fn (AccountUserDomainObject $accountUser) => $accountUser->getAccount()?->getName())
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, AccountUserDomainObject>
     */
    private function ownedEnforcingAccountUsers(int $userId): Collection
    {
        return $this->accountUserRepository
            ->loadRelation(new Relationship(AccountDomainObject::class, name: 'account'))
            ->findWhere(['user_id' => $userId, 'is_account_owner' => true, 'status' => UserStatus::ACTIVE->name])
            ->filter(fn (AccountUserDomainObject $accountUser) => $this->findConnection($accountUser->getAccountId())?->isEnforcing() === true)
            ->values();
    }

    public function shouldEnforceNewMember(int $accountId, string $email, bool $isAccountOwner): bool
    {
        if ($isAccountOwner) {
            return false;
        }

        return $this->findConnection($accountId)?->getEnforceSso() === true
            && $this->domainService->isEmailOnUsableDomain($accountId, $email);
    }

    /**
     * @param  Collection<int, UserDomainObject>  $users
     */
    public function annotateSignInRequirements(int $accountId, Collection $users): void
    {
        if ($this->findConnection($accountId)?->isEnforcing() !== true) {
            return;
        }

        $usableDomains = $this->domainService->usableDomains($accountId)
            ->map(fn (AccountSsoDomainDomainObject $domain) => $domain->getDomain())
            ->all();
        $withoutConnection = $this->usersWhoCannotUseConnection(
            $this->findConnection($accountId),
            $users->map(fn (UserDomainObject $user) => $user->getId())->all(),
        );

        $users->each(function (UserDomainObject $user) use ($usableDomains, $withoutConnection) {
            $accountUser = $user->getCurrentAccountUser();
            $onVerifiedDomain = in_array($this->domainService->domainOf($user->getEmail()), $usableDomains, true);
            $canUseConnection = ! in_array($user->getId(), $withoutConnection, true);

            $accountUser?->setSsoSignInRequirement(match (true) {
                ! $onVerifiedDomain || ! $canUseConnection || $accountUser->getIsAccountOwner() => SsoSignInRequirement::PASSWORD,
                $accountUser->getSsoEnforced() => SsoSignInRequirement::SSO,
                default => SsoSignInRequirement::EXEMPT,
            });
        });
    }

    public function preview(int $accountId): SsoEnforcementPreview
    {
        $accountUsers = $this->members($accountId);
        $withoutConnection = $this->usersWhoCannotUseConnection(
            $this->findConnection($accountId),
            $accountUsers->map(fn (AccountUserDomainObject $accountUser) => $accountUser->getUserId())->all(),
        );

        $members = $accountUsers
            ->map(fn (AccountUserDomainObject $accountUser) => new SsoEnforcementMember(
                accountUserId: $accountUser->getId(),
                userId: $accountUser->getUserId(),
                email: $accountUser->getUser()?->getEmail() ?? '',
                firstName: $accountUser->getUser()?->getFirstName(),
                lastName: $accountUser->getUser()?->getLastName(),
                role: $accountUser->getRole(),
                isAccountOwner: $accountUser->getIsAccountOwner(),
                onVerifiedDomain: $this->domainService->isEmailOnUsableDomain($accountId, $accountUser->getUser()?->getEmail()),
                ssoEnforced: $accountUser->getSsoEnforced(),
                ssoAvailable: ! in_array($accountUser->getUserId(), $withoutConnection, true),
            ))
            ->values();

        return new SsoEnforcementPreview(
            members: $members,
            affectedCount: $members->filter(fn (SsoEnforcementMember $member) => $member->onVerifiedDomain && $member->ssoAvailable && ! $member->isAccountOwner)->count(),
            outsideDomainCount: $members->filter(fn (SsoEnforcementMember $member) => ! $member->onVerifiedDomain && ! $member->isAccountOwner)->count(),
        );
    }

    /**
     * @param  int[]  $exemptAccountUserIds
     *
     * @throws AccountSsoConfigurationException
     */
    public function enable(AccountSsoConnectionDomainObject $connection, array $exemptAccountUserIds): AccountSsoConnectionDomainObject
    {
        $this->assertCanEnable($connection);

        $members = $this->members($connection->getAccountId());
        $withoutConnection = $this->usersWhoCannotUseConnection(
            $connection,
            $members->map(fn (AccountUserDomainObject $accountUser) => $accountUser->getUserId())->all(),
        );
        $newlyEnforcedUserIds = [];

        foreach ($members as $accountUser) {
            $enforce = ! $accountUser->getIsAccountOwner()
                && ! in_array($accountUser->getId(), $exemptAccountUserIds, true)
                && $this->domainService->isEmailOnUsableDomain($connection->getAccountId(), $accountUser->getUser()?->getEmail());

            if ($enforce
                && ! in_array($accountUser->getUserId(), $withoutConnection, true)
                && ! ($connection->getEnforceSso() && $accountUser->getSsoEnforced())) {
                $newlyEnforcedUserIds[] = $accountUser->getUserId();
            }

            if ($accountUser->getSsoEnforced() !== $enforce) {
                $this->accountUserRepository->updateWhere(['sso_enforced' => $enforce], ['id' => $accountUser->getId()]);
            }
        }

        $this->sessionRevocationService->revokeAllForUsers($newlyEnforcedUserIds, $connection->getAccountId());

        return $this->connectionRepository->updateFromArray($connection->getId(), ['enforce_sso' => true]);
    }

    public function disable(AccountSsoConnectionDomainObject $connection): AccountSsoConnectionDomainObject
    {
        return $this->connectionRepository->updateFromArray($connection->getId(), ['enforce_sso' => false]);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function setMemberEnforced(int $accountId, int $accountUserId, bool $enforced): void
    {
        $accountUser = $this->accountUserRepository
            ->loadRelation(new Relationship(UserDomainObject::class, name: 'user'))
            ->findFirstWhere(['id' => $accountUserId, 'account_id' => $accountId]);

        if ($accountUser === null) {
            throw new AccountSsoConfigurationException(__('Team member not found.'), 'account_user_id');
        }

        if ($enforced && ($accountUser->getIsAccountOwner() || ! $this->domainService->isEmailOnUsableDomain($accountId, $accountUser->getUser()?->getEmail()))) {
            throw new AccountSsoConfigurationException(__('Only members with an email on a verified domain can be required to use single sign-on.'), 'account_user_id');
        }

        $this->accountUserRepository->updateWhere(['sso_enforced' => $enforced], ['id' => $accountUserId]);

        $connection = $this->findConnection($accountId);

        if ($enforced
            && ! $accountUser->getSsoEnforced()
            && $connection?->isEnforcing()
            && $this->usersWhoCannotUseConnection($connection, [$accountUser->getUserId()]) === []) {
            $this->sessionRevocationService->revokeAll($accountUser->getUserId(), $accountId);
        }
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function assertEmailChangeAllowed(int $userId, string $newEmail): void
    {
        foreach ($this->enforcedMemberships($userId) as $accountUser) {
            if (! $this->domainService->isEmailOnUsableDomain($accountUser->getAccountId(), $newEmail)) {
                throw new AccountSsoConfigurationException(
                    __('Your organization requires single sign-on, so your email must stay on one of its verified domains.'),
                    'email',
                );
            }
        }
    }

    public function isPasswordResetAllowed(UserDomainObject $user): bool
    {
        $activeMemberships = $this->accountUserRepository->findWhere([
            'user_id' => $user->getId(),
            'status' => UserStatus::ACTIVE->name,
        ]);

        if ($activeMemberships->isEmpty()) {
            return true;
        }

        return $activeMemberships->contains(fn (AccountUserDomainObject $accountUser) => ! $this->memberRequiresSso($accountUser, $user->getEmail()));
    }

    private function memberRequiresSso(AccountUserDomainObject $accountUser, string $email): bool
    {
        if (! $accountUser->getSsoEnforced() || $accountUser->getIsAccountOwner()) {
            return false;
        }

        $connection = $this->findConnection($accountUser->getAccountId());

        return $connection?->isEnforcing() === true
            && $this->domainService->isEmailOnUsableDomain($accountUser->getAccountId(), $email)
            && $this->usersWhoCannotUseConnection($connection, [$accountUser->getUserId()]) === [];
    }

    /**
     * @param  int[]  $userIds
     * @return int[]
     */
    private function usersWhoCannotUseConnection(?AccountSsoConnectionDomainObject $connection, array $userIds): array
    {
        if ($connection === null || $userIds === []) {
            return [];
        }

        $accountUsers = $this->accountUserRepository->findWhere([['user_id', 'in', $userIds]]);
        $superAdminIds = $accountUsers
            ->filter(fn (AccountUserDomainObject $accountUser) => $accountUser->getRole() === Role::SUPERADMIN->name)
            ->map(fn (AccountUserDomainObject $accountUser) => $accountUser->getUserId())
            ->unique();
        $ownerIds = $accountUsers
            ->filter(fn (AccountUserDomainObject $accountUser) => $accountUser->getIsAccountOwner())
            ->map(fn (AccountUserDomainObject $accountUser) => $accountUser->getUserId())
            ->unique()
            ->diff($superAdminIds);

        $linkedOwnerIds = $ownerIds->isEmpty() ? collect() : $this->userIdentityRepository
            ->findWhere([['user_id', 'in', $ownerIds->all()]])
            ->filter(fn (UserIdentityDomainObject $identity) => $this->identitySignsInThrough($identity, $connection))
            ->map(fn (UserIdentityDomainObject $identity) => $identity->getUserId());

        return $superAdminIds->merge($ownerIds->diff($linkedOwnerIds))->values()->all();
    }

    private function identitySignsInThrough(UserIdentityDomainObject $identity, AccountSsoConnectionDomainObject $connection): bool
    {
        if ($identity->getAccountSsoConnectionId() === $connection->getId()) {
            return true;
        }

        if ($identity->getIssuer() !== $connection->getIssuer()) {
            return false;
        }

        return $connection->getConnectionType() !== AccountSsoConnectionType::GOOGLE_WORKSPACE
            || ($identity->getEmail() !== null && $this->domainService->domainOf($identity->getEmail()) === $connection->getHostedDomain());
    }

    /**
     * @return Collection<int, AccountUserDomainObject>
     */
    private function enforcedMemberships(int $userId): Collection
    {
        return $this->accountUserRepository
            ->findWhere(['user_id' => $userId, 'sso_enforced' => true])
            ->filter(fn (AccountUserDomainObject $accountUser) => $this->findConnection($accountUser->getAccountId())?->isEnforcing() === true)
            ->values();
    }

    /**
     * @return Collection<int, AccountUserDomainObject>
     */
    private function members(int $accountId): Collection
    {
        return $this->accountUserRepository
            ->loadRelation(new Relationship(UserDomainObject::class, name: 'user'))
            ->findWhere([
                'account_id' => $accountId,
                ['status', 'in', [UserStatus::ACTIVE->name, UserStatus::INVITED->name]],
            ]);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    private function assertCanEnable(AccountSsoConnectionDomainObject $connection): void
    {
        if (! $connection->isActive()) {
            throw new AccountSsoConfigurationException(__('Turn on the connection before requiring single sign-on.'), 'enforce_sso');
        }

        if ($this->domainService->usableDomains($connection->getAccountId())->isEmpty()) {
            throw new AccountSsoConfigurationException(__('Verify at least one domain before requiring single sign-on.'), 'enforce_sso');
        }

        $owner = $this->accountUserRepository->findFirstWhere([
            'account_id' => $connection->getAccountId(),
            'is_account_owner' => true,
        ]);

        $ownerUser = $owner === null ? null : $this->userRepository->findFirstWhere(['id' => $owner->getUserId()]);

        if ($ownerUser === null || ! $this->twoFactorAuthenticationService->isEnabled($ownerUser)) {
            throw new AccountSsoConfigurationException(
                __('The account owner must turn on two-factor authentication first. It protects the owner\'s backup sign-in if your identity provider is unavailable.'),
                'enforce_sso',
            );
        }
    }
}
