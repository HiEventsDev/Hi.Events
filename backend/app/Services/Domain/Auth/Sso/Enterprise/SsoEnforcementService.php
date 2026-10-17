<?php

namespace HiEvents\Services\Domain\Auth\Sso\Enterprise;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\AuthMethod;
use HiEvents\DomainObjects\Enums\SsoSignInRequirement;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Exceptions\SsoRequiredException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\DTO\SsoEnforcementMember;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\DTO\SsoEnforcementPreview;
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

        return $this->accountUserRepository
            ->findWhere(['user_id' => $userId, 'is_account_owner' => true, 'status' => UserStatus::ACTIVE->name])
            ->contains(fn (AccountUserDomainObject $accountUser) => $this->findConnection($accountUser->getAccountId())?->isEnforcing() === true);
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

        $users->each(function (UserDomainObject $user) use ($usableDomains) {
            $accountUser = $user->getCurrentAccountUser();
            $onVerifiedDomain = in_array($this->domainService->domainOf($user->getEmail()), $usableDomains, true);

            $accountUser?->setSsoSignInRequirement(match (true) {
                $onVerifiedDomain && $accountUser->getSsoEnforced() && ! $accountUser->getIsAccountOwner() => SsoSignInRequirement::SSO,
                $onVerifiedDomain && ! $accountUser->getIsAccountOwner() => SsoSignInRequirement::EXEMPT,
                default => SsoSignInRequirement::PASSWORD,
            });
        });
    }

    public function preview(int $accountId): SsoEnforcementPreview
    {
        $members = $this->members($accountId)
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
            ))
            ->values();

        return new SsoEnforcementPreview(
            members: $members,
            affectedCount: $members->filter(fn (SsoEnforcementMember $member) => $member->onVerifiedDomain && ! $member->isAccountOwner)->count(),
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

        $newlyEnforcedUserIds = [];

        foreach ($this->members($connection->getAccountId()) as $accountUser) {
            $enforce = ! $accountUser->getIsAccountOwner()
                && ! in_array($accountUser->getId(), $exemptAccountUserIds, true)
                && $this->domainService->isEmailOnUsableDomain($connection->getAccountId(), $accountUser->getUser()?->getEmail());

            if ($enforce && ! ($connection->getEnforceSso() && $accountUser->getSsoEnforced())) {
                $newlyEnforcedUserIds[] = $accountUser->getUserId();
            }

            if ($accountUser->getSsoEnforced() !== $enforce) {
                $this->accountUserRepository->updateWhere(['sso_enforced' => $enforce], ['id' => $accountUser->getId()]);
            }
        }

        $this->sessionRevocationService->revokeAllForUsers($newlyEnforcedUserIds);

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

        if ($enforced && ! $accountUser->getSsoEnforced() && $this->findConnection($accountId)?->isEnforcing()) {
            $this->sessionRevocationService->revokeAll($accountUser->getUserId());
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

        return $this->findConnection($accountUser->getAccountId())?->isEnforcing() === true
            && $this->domainService->isEmailOnUsableDomain($accountUser->getAccountId(), $email);
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
