<?php

namespace HiEvents\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Exceptions\SsoException;
use HiEvents\Mail\User\SignInMethodChangedMail;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Illuminate\Contracts\Mail\Mailer;

class UserIdentityService
{
    public function __construct(
        private readonly UserIdentityRepositoryInterface $userIdentityRepository,
        private readonly SsoProviderRegistry $providerRegistry,
        private readonly Mailer $mailer,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly UserSessionRevocationService $sessionRevocationService,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
    ) {}

    public function usableConnectionProvider(UserIdentityDomainObject $identity): ?SsoProviderConfig
    {
        if (! str_starts_with($identity->getProviderKey(), AccountSsoConnectionDomainObject::PROVIDER_KEY_PREFIX)) {
            return null;
        }

        try {
            $provider = $this->providerRegistry->get($identity->getProviderKey());
        } catch (SsoException) {
            return null;
        }

        $isActiveMember = $this->accountUserRepository->findFirstWhere([
            'user_id' => $identity->getUserId(),
            'account_id' => $provider->accountId,
            'status' => UserStatus::ACTIVE->name,
        ]) !== null;

        return $isActiveMember ? $provider : null;
    }

    public function find(SsoIdentityClaims $claims): ?UserIdentityDomainObject
    {
        return $this->userIdentityRepository->findFirstWhere([
            'issuer' => $claims->issuer,
            'subject' => $claims->subject,
        ]);
    }

    /**
     * @throws SsoException
     */
    public function attach(UserDomainObject $user, SsoIdentityClaims $claims, bool $notify = true): UserIdentityDomainObject
    {
        $existing = $this->find($claims);

        if ($existing !== null && $existing->getUserId() !== $user->getId()) {
            throw new SsoException(SsoErrorCode::IDENTITY_IN_USE, __('This account is already connected to another user.'));
        }

        if ($existing !== null) {
            return $this->touch($existing, $claims);
        }

        $identity = $this->userIdentityRepository->create([
            'user_id' => $user->getId(),
            'account_sso_connection_id' => $claims->connectionId,
            'provider_key' => $claims->providerKey,
            'issuer' => $claims->issuer,
            'subject' => $claims->subject,
            'email' => $claims->email,
            'last_used_at' => now()->toDateTimeString(),
        ]);

        if ($notify) {
            $this->notify($user, $claims->providerKey, connected: true);
        }

        return $identity;
    }

    /**
     * @throws SsoException
     */
    public function attachForSignIn(UserDomainObject $user, SsoIdentityClaims $claims): void
    {
        $existing = $this->find($claims);

        if ($existing !== null && $existing->getUserId() !== $user->getId()) {
            throw new SsoException(SsoErrorCode::IDENTITY_IN_USE, __('This account is already connected to another user.'));
        }

        if ($existing !== null) {
            $this->touch($existing, $claims);

            return;
        }

        $hadOtherSignIn = $user->getPassword() !== null || $this->hasIdentity($user->getId());

        if ($this->providerRegistry->isPasswordLoginDisabled() && ! $this->hasIdentity($user->getId())) {
            $this->replaceEarlierCredentials($user->getId());
        }

        $this->attach($user, $claims, notify: $hadOtherSignIn);
    }

    public function touch(UserIdentityDomainObject $identity, SsoIdentityClaims $claims): UserIdentityDomainObject
    {
        return $this->userIdentityRepository->updateFromArray($identity->getId(), [
            'email' => $claims->email,
            'last_used_at' => now()->toDateTimeString(),
        ]);
    }

    public function notify(UserDomainObject $user, string $providerKey, bool $connected): void
    {
        $providerName = $this->providerName($providerKey) ?? __('single sign-on');

        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new SignInMethodChangedMail($providerName, $connected));
    }

    public function providerNamesFor(int $userId): ?string
    {
        $names = $this->userIdentityRepository
            ->findWhere(['user_id' => $userId])
            ->map(fn (UserIdentityDomainObject $identity) => $this->providerName($identity->getProviderKey()))
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? null : $names->join(', ');
    }

    private function providerName(string $providerKey): ?string
    {
        try {
            return $this->providerRegistry->get($providerKey, includeInactiveConnection: true)->name;
        } catch (SsoException) {
            return null;
        }
    }

    public function hasIdentity(int $userId): bool
    {
        return $this->userIdentityRepository->findFirstWhere(['user_id' => $userId]) !== null;
    }

    private function replaceEarlierCredentials(int $userId): void
    {
        $this->userRepository->updateWhere(
            attributes: ['password' => null],
            where: ['id' => $userId],
        );
        $this->trustedDeviceService->revokeAll($userId);
        $this->sessionRevocationService->revokeAll($userId);
    }

    public function deleteForUser(int $userId): void
    {
        $this->userIdentityRepository->deleteWhere(['user_id' => $userId]);
    }
}
