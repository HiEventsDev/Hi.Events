<?php

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use Illuminate\Auth\AuthManager;
use Laravel\Sanctum\PersonalAccessToken;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Throwable;

class AuthUserService
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
    ) {}

    public function getAuthenticatedAccountId(): ?int
    {
        if (! $this->authManager->check()) {
            return null;
        }

        if ($apiKey = $this->currentApiKey()) {
            return (int) $apiKey->getAttribute('account_id');
        }

        $accountId = $this->sessionClaim('account_id');

        return $accountId === null ? null : (int) $accountId;
    }

    public function getAuthenticatedTokenKey(): ?string
    {
        if ($apiKey = $this->currentApiKey()) {
            return 'api-key:'.$apiKey->getKey();
        }

        try {
            $token = $this->authManager->guard()->getToken();
        } catch (Throwable) {
            return null;
        }

        return $token === null ? null : (string) $token;
    }

    public function getAuthenticatedUserId(): ?int
    {
        if (! $this->authManager->check()) {
            return null;
        }

        return (int) $this->authManager->id();
    }

    public function isApiKeyRequest(): bool
    {
        return $this->currentApiKey() !== null;
    }

    public function apiKeyCanWrite(): bool
    {
        $apiKey = $this->currentApiKey();

        return $apiKey === null || $apiKey->can(ApiKeyAccess::WRITE_ABILITY);
    }

    public function isImpersonating(): bool
    {
        return (bool) $this->sessionClaim('is_impersonating', false);
    }

    public function getImpersonatorId(): ?int
    {
        if (! $this->isImpersonating()) {
            return null;
        }

        $impersonatorId = $this->sessionClaim('impersonator_id');

        return $impersonatorId === null ? null : (int) $impersonatorId;
    }

    public function getSessionTokenId(): ?string
    {
        $tokenId = $this->sessionClaim('jti');

        return is_string($tokenId) ? $tokenId : null;
    }

    public function logout(): void
    {
        if (! $this->isApiKeyRequest()) {
            $this->authManager->logout();
        }
    }

    public function getUser(): UserDomainObject|DomainObjectInterface|null
    {
        /** @var User $user */
        if ($user = $this->authManager->user()) {
            $user = UserDomainObject::hydrateFromModel($user);

            if ($accountId = $this->getAuthenticatedAccountId()) {
                $user->setCurrentAccountUser($this->accountUserRepository->findFirstWhere([
                    'user_id' => $user->getId(),
                    'account_id' => $accountId,
                ]));
            }

            return $user;
        }

        return null;
    }

    private function currentApiKey(): ?PersonalAccessToken
    {
        $user = $this->authManager->user();

        if (! $user instanceof User) {
            return null;
        }

        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    private function sessionClaim(string $claim, mixed $default = null): mixed
    {
        if (! $this->authManager->check() || $this->isApiKeyRequest()) {
            return $default;
        }

        try {
            return $this->authManager->payload()->get($claim) ?? $default;
        } catch (JWTException) {
            return $default;
        }
    }
}
