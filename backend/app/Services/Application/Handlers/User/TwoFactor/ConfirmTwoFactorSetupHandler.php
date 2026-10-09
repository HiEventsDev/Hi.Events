<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor;

use HiEvents\Exceptions\InvalidTwoFactorCodeException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\TwoFactorLockedOutException;
use HiEvents\Mail\User\TwoFactorEnabledMail;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\RecoveryCodeService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorVerifier;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;

class ConfirmTwoFactorSetupHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly TwoFactorVerifier $twoFactorVerifier,
        private readonly DatabaseManager $databaseManager,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @return string[]
     *
     * @throws ResourceConflictException
     * @throws InvalidTwoFactorCodeException
     * @throws TwoFactorLockedOutException
     */
    public function handle(int $userId, string $code): array
    {
        $user = $this->userRepository->findById($userId);

        if (! $this->twoFactorAuthenticationService->hasPendingSetup($user)) {
            throw new ResourceConflictException(__('Start two-factor setup again to get a new QR code.'));
        }

        if (! $this->twoFactorVerifier->verify($user, $code)) {
            throw new InvalidTwoFactorCodeException(__('That code is not valid. Check your authenticator app and try again.'));
        }

        $recoveryCodes = $this->databaseManager->transaction(function () use ($user) {
            $this->twoFactorAuthenticationService->confirmSetup($user);

            return $this->recoveryCodeService->regenerate($user->getId());
        });

        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new TwoFactorEnabledMail($user));

        return $recoveryCodes;
    }
}
