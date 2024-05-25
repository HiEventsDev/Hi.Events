<?php

namespace HiEvents\Services\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Services\Domain\User\EmailConfirmationService;

class ResendEmailConfirmationHandler
{
    public function __construct(
        private readonly EmailConfirmationService $emailConfirmationService,
    )
    {
    }

    public function handle(UserDomainObject $user): void
    {
        $this->emailConfirmationService->sendConfirmation($user);
    }
}
