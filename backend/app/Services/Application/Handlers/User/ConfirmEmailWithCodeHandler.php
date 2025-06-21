<?php

namespace HiEvents\Services\Application\Handlers\User;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\ConfirmEmailWithCodeDTO;
use HiEvents\Services\Application\Handlers\User\Exception\InvalidEmailVerificationCodeException;
use HiEvents\Services\Domain\User\VerifyUserEmailService;
use HiEvents\Services\Infrastructure\User\EmailVerificationCodeService;
use Illuminate\Database\DatabaseManager;

class ConfirmEmailWithCodeHandler
{
    public function __construct(
        private readonly EmailVerificationCodeService $emailVerificationCodeService,
        private readonly UserRepositoryInterface      $userRepository,
        private readonly DatabaseManager              $databaseManager,
        private readonly VerifyUserEmailService       $verifyUserEmailService,
    )
    {
    }

    public function handle(ConfirmEmailWithCodeDTO $dto): void
    {
        $this->databaseManager->transaction(function () use ($dto) {
            $user = $this->userRepository->findByIdAndAccountId($dto->userId, $dto->accountId);

            if ($user->getEmailVerifiedAt() !== null) {
                throw new ResourceConflictException(__('Your email address has already been verified.'));
            }

            if (!$this->emailVerificationCodeService->verifyCode($user->getEmail(), $dto->code)) {
                throw new InvalidEmailVerificationCodeException(__('The verification code is invalid or has expired.'));
            }

            $this->verifyUserEmailService->markEmailAsVerified($user, $dto->accountId);
        });
    }
}
