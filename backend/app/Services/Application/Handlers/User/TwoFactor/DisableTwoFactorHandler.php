<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor;

use HiEvents\Exceptions\InvalidTwoFactorCodeException;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\TwoFactorLockedOutException;
use HiEvents\Exceptions\TwoFactorRequiredByAccountException;
use HiEvents\Mail\User\TwoFactorDisabledMail;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\TwoFactor\DTO\DisableTwoFactorDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorRequirementService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorVerifier;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;

class DisableTwoFactorHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly TwoFactorVerifier $twoFactorVerifier,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly TwoFactorRequirementService $twoFactorRequirementService,
        private readonly Hasher $hasher,
        private readonly DatabaseManager $databaseManager,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws TwoFactorRequiredByAccountException
     * @throws PasswordInvalidException
     * @throws InvalidTwoFactorCodeException
     * @throws TwoFactorLockedOutException
     */
    public function handle(DisableTwoFactorDTO $dto): void
    {
        $user = $this->userRepository->findById($dto->userId);

        if (! $this->twoFactorAuthenticationService->isEnabled($user)) {
            throw new ResourceConflictException(__('Two-factor authentication is not enabled.'));
        }

        $requiringAccounts = $this->twoFactorRequirementService->namesOfAccountsRequiringTwoFactor($user->getId());

        if ($requiringAccounts->isNotEmpty()) {
            throw new TwoFactorRequiredByAccountException(__(
                'Two-factor authentication is required by :accounts, so it cannot be turned off.',
                ['accounts' => $requiringAccounts->join(', ')],
            ));
        }

        if (! $this->hasher->check($dto->password, $user->getPassword())) {
            throw new PasswordInvalidException(__('The password is incorrect.'));
        }

        if (! $this->twoFactorVerifier->verify($user, $dto->code, $dto->recoveryCode)) {
            throw new InvalidTwoFactorCodeException(__('That code is not valid. Check your authenticator app and try again.'));
        }

        $this->databaseManager->transaction(function () use ($user) {
            $this->twoFactorAuthenticationService->disable($user->getId());
            $this->trustedDeviceService->revokeAll($user->getId());
        });

        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new TwoFactorDisabledMail($user));
    }
}
