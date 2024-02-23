<?php

namespace TicketKitten\Service\Handler\User;

use Throwable;
use TicketKitten\Exceptions\DecryptionFailedException;
use TicketKitten\Service\Common\User\EmailConfirmationService;

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
