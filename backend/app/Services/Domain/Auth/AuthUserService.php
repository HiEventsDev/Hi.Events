<?php

namespace HiEvents\Services\Domain\Auth;

use Illuminate\Auth\AuthManager;
use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\User;

class AuthUserService
{
    private AuthManager $authManager;

    public function __construct(AuthManager $authManager)
    {
        $this->authManager = $authManager;
    }

    public function getUser(): UserDomainObject|DomainObjectInterface|null
    {
        /** @var User $user */
        if ($user = $this->authManager->user()) {
            return UserDomainObject::hydrateFromModel($user);
        }

        return null;
    }
}
