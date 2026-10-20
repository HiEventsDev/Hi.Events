<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;
use HiEvents\Services\Domain\Auth\PasswordLoginPolicy;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class DeleteSsoIdentityHandler
{
    public function __construct(
        private readonly IdentityConfirmationService $identityConfirmationService,
        private readonly UserIdentityRepositoryInterface $userIdentityRepository,
        private readonly UserIdentityService $userIdentityService,
        private readonly UserSessionRevocationService $sessionRevocationService,
        private readonly SsoProviderRegistry $providerRegistry,
        private readonly PasswordLoginPolicy $passwordLoginPolicy,
    ) {}

    /**
     * @throws IdentityConfirmationFailedException
     * @throws SsoException
     */
    public function handle(UserDomainObject $user, int $identityId, ?string $password, ?string $reauthProof, ?string $tokenId): void
    {
        $identity = $this->userIdentityRepository->findFirstWhere([
            'id' => $identityId,
            'user_id' => $user->getId(),
        ]);

        if ($identity === null) {
            throw new ResourceNotFoundException(__('Sign-in method not found'));
        }

        $usableProviders = array_keys($this->providerRegistry->all());
        $remaining = $this->userIdentityRepository
            ->findWhere(['user_id' => $user->getId()])
            ->filter(fn (UserIdentityDomainObject $other) => $other->getId() !== $identity->getId()
                && $this->canSignInWith($other, $usableProviders))
            ->count();

        if (! $this->passwordLoginPolicy->canConfirmWithPassword($user) && $remaining === 0) {
            throw new SsoException(
                SsoErrorCode::LAST_SIGN_IN_METHOD,
                __('Add a password or another sign-in method before removing this one.'),
            );
        }

        $this->identityConfirmationService->confirm($user, $password, $reauthProof, $tokenId);

        $this->userIdentityRepository->deleteWhere(['id' => $identity->getId()]);
        $this->sessionRevocationService->revokeAll($user->getId());
        $this->userIdentityService->notify($user, $identity->getProviderKey(), connected: false);
    }

    /**
     * @param  string[]  $usableProviders
     */
    private function canSignInWith(UserIdentityDomainObject $identity, array $usableProviders): bool
    {
        return in_array($identity->getProviderKey(), $usableProviders, true)
            || $this->userIdentityService->usableConnectionProvider($identity) !== null;
    }
}
