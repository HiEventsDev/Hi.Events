<?php

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use Illuminate\Auth\AuthManager;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Payload;
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

        try {
            /** @var Payload $payload */
            $payload = $this->authManager->payload();
        } catch (JWTException) {
            return null;
        }

        return $payload->get('account_id');
    }

    public function getAuthenticatedTokenKey(): ?string
    {
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
}
