<?php

namespace HiEvents\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use Illuminate\Contracts\Config\Repository as Config;
use Psr\Log\LoggerInterface;

class SsoIdentityResolver
{
    public function __construct(
        private readonly UserIdentityService $userIdentityService,
        private readonly UserIdentityRepositoryInterface $userIdentityRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly SsoProviderRegistry $providerRegistry,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws SsoException
     */
    public function resolve(SsoIdentityClaims $claims): ?UserDomainObject
    {
        $linkedUser = $this->findLinkedUser($claims);

        if ($linkedUser !== null) {
            $this->assertHasActiveMembership($linkedUser);

            return $linkedUser;
        }

        if ($claims->email === null) {
            return null;
        }

        $matches = $this->userRepository->findByEmailIgnoringCase($claims->email);

        if ($matches->isEmpty()) {
            return null;
        }

        if (! $claims->emailTrusted || $matches->count() > 1) {
            $this->logOutcome($claims, $matches->count() > 1 ? 'ambiguous_email' : 'untrusted_email');

            throw $this->linkRequiresLogin($claims);
        }

        /** @var UserDomainObject $user */
        $user = $matches->first();

        $this->assertHasActiveMembership($user);

        if ($this->isSuperAdmin($user) || ! $this->canAutoLink($user)) {
            $this->logOutcome($claims, 'link_requires_login');

            throw $this->linkRequiresLogin($claims);
        }

        return $user;
    }

    public function emailIsTaken(string $email): bool
    {
        return $this->userRepository->findByEmailIgnoringCase($email)->isNotEmpty();
    }

    private function findLinkedUser(SsoIdentityClaims $claims): ?UserDomainObject
    {
        $identity = $this->userIdentityService->find($claims);

        if ($identity === null) {
            return null;
        }

        $user = $this->userRepository->findFirstWhere(['id' => $identity->getUserId()]);

        if ($user === null) {
            $this->userIdentityRepository->deleteWhere(['id' => $identity->getId()]);
        }

        return $user;
    }

    /**
     * @throws SsoException
     */
    private function assertHasActiveMembership(UserDomainObject $user): void
    {
        $statuses = $this->accountUserRepository
            ->findWhere(['user_id' => $user->getId()])
            ->map(fn ($accountUser) => $accountUser->getStatus());

        if ($statuses->contains(UserStatus::ACTIVE->name)) {
            return;
        }

        if ($statuses->contains(UserStatus::INVITED->name)) {
            throw new SsoException(
                SsoErrorCode::INVITATION_PENDING,
                __('You have been invited to join a team. Open the invitation email to finish joining.'),
            );
        }

        throw new SsoException(SsoErrorCode::NO_ACTIVE_ACCOUNT, __('There is no active account for this user.'));
    }

    private function isSuperAdmin(UserDomainObject $user): bool
    {
        return $this->accountUserRepository->findFirstWhere([
            'user_id' => $user->getId(),
            'role' => Role::SUPERADMIN->name,
        ]) !== null;
    }

    private function canAutoLink(UserDomainObject $user): bool
    {
        if ($this->providerRegistry->isPasswordLoginDisabled()) {
            return true;
        }

        return $this->config->get('app.saas_mode_enabled') && $user->getEmailVerifiedAt() !== null;
    }

    private function linkRequiresLogin(SsoIdentityClaims $claims): SsoException
    {
        return new SsoException(
            SsoErrorCode::LINK_REQUIRES_LOGIN,
            __('Sign in with your email and password, then connect this sign-in option from your security settings.'),
            $claims->emailTrusted ? ['email' => $claims->email] : [],
        );
    }

    private function logOutcome(SsoIdentityClaims $claims, string $outcome): void
    {
        $this->logger->info('SSO identity resolution', [
            'provider' => $claims->providerKey,
            'outcome' => $outcome,
        ]);
    }
}
