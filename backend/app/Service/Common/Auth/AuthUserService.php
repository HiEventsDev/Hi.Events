<?php

namespace TicketKitten\Service\Common\Auth;

use Illuminate\Auth\AuthManager;
use TicketKitten\DomainObjects\Interfaces\DomainObjectInterface;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Models\User;

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
