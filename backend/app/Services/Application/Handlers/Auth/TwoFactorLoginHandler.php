<?php

namespace HiEvents\Services\Application\Handlers\Auth;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\InvalidTwoFactorCodeException;
use HiEvents\Exceptions\TwoFactorChallengeExpiredException;
use HiEvents\Exceptions\TwoFactorLockedOutException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Mail\User\TwoFactorRecoveryCodeUsedMail;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Auth\DTO\TwoFactorLoginDTO;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Auth\TwoFactor\DTO\TwoFactorChallengeDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\RecoveryCodeService;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorChallengeService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorVerifier;
use Illuminate\Contracts\Mail\Mailer;

class TwoFactorLoginHandler
{
    public function __construct(
        private readonly TwoFactorChallengeService $challengeService,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly TwoFactorVerifier $twoFactorVerifier,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly LoginService $loginService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws TwoFactorChallengeExpiredException
     * @throws InvalidTwoFactorCodeException
     * @throws UnauthorizedException
     */
    public function handle(TwoFactorLoginDTO $dto): LoginResponse
    {
        $challenge = $this->challengeService->find($dto->challengeToken);
        $user = $challenge !== null
            ? $this->userRepository->findFirstWhere(['id' => $challenge->userId])
            : null;

        if ($challenge === null || $user === null || ! $this->twoFactorAuthenticationService->isEnabled($user)) {
            throw new TwoFactorChallengeExpiredException(__('Your sign-in session has expired. Please sign in again.'));
        }

        $trustedDeviceToken = null;

        if (! $challenge->verified) {
            $challenge = $this->verify($dto, $challenge, $user);

            if ($dto->rememberDevice) {
                $trustedDeviceToken = $this->trustedDeviceService->trust($user->getId(), $dto->userAgent, $dto->ipAddress);
            }
        }

        $loginResponse = $this->loginService->completeLogin($user, $dto->accountId);

        if ($loginResponse->accountId !== null) {
            $this->challengeService->forget($dto->challengeToken);
            $this->accountUserRepository->updateWhere(
                attributes: ['last_login_at' => now()],
                where: [
                    'user_id' => $user->getId(),
                    'account_id' => $loginResponse->accountId,
                ],
            );
        }

        return new LoginResponse(
            accounts: $loginResponse->accounts,
            token: $loginResponse->token,
            user: $loginResponse->user,
            accountId: $loginResponse->accountId,
            recoveryCodesRemaining: $challenge->recoveryCodesRemaining,
            trustedDeviceToken: $trustedDeviceToken,
        );
    }

    /**
     * @throws TwoFactorChallengeExpiredException
     * @throws InvalidTwoFactorCodeException
     */
    private function verify(TwoFactorLoginDTO $dto, TwoFactorChallengeDTO $challenge, UserDomainObject $user): TwoFactorChallengeDTO
    {
        $usingRecoveryCode = $dto->recoveryCode !== null;

        try {
            $verified = $this->twoFactorVerifier->verify($user, $dto->code, $dto->recoveryCode);
        } catch (TwoFactorLockedOutException $exception) {
            $this->challengeService->forget($dto->challengeToken);

            throw new TwoFactorChallengeExpiredException($exception->getMessage());
        }

        if (! $verified) {
            throw new InvalidTwoFactorCodeException($usingRecoveryCode
                ? __('That recovery code is not valid or has already been used.')
                : __('That code is not valid. Check your authenticator app and try again.'));
        }

        $recoveryCodesRemaining = null;

        if ($usingRecoveryCode) {
            $recoveryCodesRemaining = $this->recoveryCodeService->remaining(
                $this->userRepository->findById($user->getId())
            );
            $this->sendRecoveryCodeUsedEmail($user, $recoveryCodesRemaining);
        }

        return $this->challengeService->markVerified($dto->challengeToken, $challenge, $recoveryCodesRemaining);
    }

    private function sendRecoveryCodeUsedEmail(UserDomainObject $user, int $recoveryCodesRemaining): void
    {
        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new TwoFactorRecoveryCodeUsedMail($user, $recoveryCodesRemaining));
    }
}
