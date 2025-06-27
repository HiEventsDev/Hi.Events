<?php

namespace HiEvents\Services\Application\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Services\Domain\User\EmailConfirmationService;

class ResendEmailConfirmationHandler
{
    public function __construct(
        private readonly EmailConfirmationService $emailConfirmationService,
    )
    {
    }

    public function handle(UserDomainObject $user, int $accountId): void
    {
        $this->emailConfirmationService->sendConfirmation($user, $accountId);
    }
}
