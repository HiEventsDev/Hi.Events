<?php

namespace HiEvents\Services\Handlers\User;

use HiEvents\Exceptions\DecryptionFailedException;
use HiEvents\Services\Common\User\EmailConfirmationService;
use Throwable;

readonly class ConfirmEmailAddressHandler
{
    public function __construct(
        private EmailConfirmationService $emailConfirmationService,
    )
    {
    }

    /**
     * @throws DecryptionFailedException|Throwable
     */
    public function handle(string $token): void
    {
        $this->emailConfirmationService->confirmEmailAddress($token);
    }
}
