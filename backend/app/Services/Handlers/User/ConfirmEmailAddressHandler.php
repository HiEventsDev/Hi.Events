<?php

namespace HiEvents\Services\Handlers\User;

use HiEvents\Services\Domain\User\EmailConfirmationService;
use HiEvents\Services\Handlers\User\DTO\ConfirmEmailChangeDTO;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
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
    public function handle(ConfirmEmailChangeDTO $data): void
    {
        $this->emailConfirmationService->confirmEmailAddress($data->token, $data->accountId);
    }
}
