<?php

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthManager;
use Laravel\Sanctum\TransientToken;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Payload;

readonly class AuthUserService
{
    public function __construct(
        /**
         * @var AuthManager
         */
        private AuthManager                    $authManager,
        private AccountUserRepositoryInterface $accountUserRepository,
    )
    {
    }

    public function getAuthenticatedAccountId(): ?int
    {
        if (!$this->authManager->check()) {
            return null;
        }

        if (Auth::user()->currentAccessToken()) {
            if (Auth::user()->currentAccessToken() instanceof TransientToken) {
                // assume logged in
                return auth()->guard('api')->payload()->get('account_id');
            } else {
                return Auth::user()->currentAccessToken()->account_id;
            }
        }

        try {
            /** @var Payload $payload */
            $payload = $this->authManager->payload();
        } catch (JWTException) {
            return null;
        }

        return $payload->get('account_id');
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
