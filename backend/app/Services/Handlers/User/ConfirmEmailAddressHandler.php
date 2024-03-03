<?php

namespace HiEvents\Services\Handlers\User;

use HiEvents\Services\Domain\User\EmailConfirmationService;
use HiEvents\Services\Infrastructure\Encyption\Exception\DecryptionFailedException;
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
