<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use Psr\Log\LoggerInterface;

class EnterpriseSsoResolver
{
    public function __construct(
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
        private readonly UserIdentityService $userIdentityService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly AccountSsoDomainService $domainService,
        private readonly SsoJitProvisioningService $jitProvisioningService,
        private readonly SsoConnectionLinkService $connectionLinkService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws SsoException
     */
    public function resolve(SsoProviderConfig $provider, SsoIdentityClaims $claims): UserDomainObject
    {
        /** @var AccountSsoConnectionDomainObject $connection */
        $connection = $this->connectionRepository->findById((int) $provider->connectionId);
        $accountId = $connection->getAccountId();
        $claimedEmailTrusted = $claims->emailTrusted && $this->domainService->isEmailOnUsableDomain($accountId, $claims->email);

        $linkedUser = $this->findLinkedUser($claims);

        if ($linkedUser !== null && $this->connectionLinkService->isSuperAdmin($linkedUser->getId())) {
            $this->logOutcome($connection, 'superadmin');

            throw $this->signInWithPasswordInstead();
        }

        $user = $linkedUser ?? $this->findOrCreateByEmail($connection, $claims, $claimedEmailTrusted);

        $this->jitProvisioningService->ensureMembership(
            connection: $connection,
            user: $user,
            userEmailOnVerifiedDomain: $claimedEmailTrusted && $this->domainService->isEmailOnUsableDomain($accountId, $user->getEmail()),
        );

        return $user;
    }

    private function findLinkedUser(SsoIdentityClaims $claims): ?UserDomainObject
    {
        $identity = $this->userIdentityService->find($claims);

        return $identity === null ? null : $this->userRepository->findFirstWhere(['id' => $identity->getUserId()]);
    }

    /**
     * @throws SsoException
     */
    private function findOrCreateByEmail(AccountSsoConnectionDomainObject $connection, SsoIdentityClaims $claims, bool $emailTrusted): UserDomainObject
    {
        if ($claims->email === null) {
            throw new SsoException(
                SsoErrorCode::EMAIL_REQUIRED,
                __('Your identity provider did not share your email address. Ask your administrator to include it.'),
            );
        }

        $matches = $this->userRepository->findByEmailIgnoringCase($claims->email);

        if ($matches->count() > 1 || ($matches->count() === 1 && (! $emailTrusted || $this->isProtectedFromEmailLinking($matches->first())))) {
            $this->logOutcome($connection, $matches->count() > 1 ? 'ambiguous_email' : 'link_requires_login');

            $canConnect = $matches->count() === 1
                && $emailTrusted
                && $this->connectionLinkService->canLink($connection->getAccountId(), $matches->first()->getId());

            throw $canConnect
                ? new SsoException(
                    SsoErrorCode::LINK_REQUIRES_LOGIN,
                    __('Sign in with your email and password, then connect this sign-in option from your security settings.'),
                    ['email' => $claims->email],
                )
                : $this->signInWithPasswordInstead();
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if (! $emailTrusted || ! $connection->getJitProvisioningEnabled()) {
            $this->logOutcome($connection, $emailTrusted ? 'jit_disabled' : 'untrusted_email');

            throw new SsoException(
                SsoErrorCode::NO_ACTIVE_ACCOUNT,
                __('You don\'t have access to this account yet. Ask your administrator to invite you.'),
            );
        }

        return $this->jitProvisioningService->createUser($connection, $claims);
    }

    public function hasMembershipsOutside(int $userId, int $accountId): bool
    {
        return $this->accountUserRepository->findFirstWhere([
            'user_id' => $userId,
            ['account_id', '!=', $accountId],
        ]) !== null;
    }

    private function isProtectedFromEmailLinking(UserDomainObject $user): bool
    {
        return $this->connectionLinkService->isSuperAdmin($user->getId()) || $this->accountUserRepository->findFirstWhere([
            'user_id' => $user->getId(),
            'is_account_owner' => true,
        ]) !== null;
    }

    private function signInWithPasswordInstead(): SsoException
    {
        return new SsoException(SsoErrorCode::LINK_REQUIRES_LOGIN, __('Sign in with your email and password instead.'));
    }

    private function logOutcome(AccountSsoConnectionDomainObject $connection, string $outcome): void
    {
        $this->logger->info('Enterprise SSO identity resolution', [
            'connection_id' => $connection->getId(),
            'outcome' => $outcome,
        ]);
    }
}
