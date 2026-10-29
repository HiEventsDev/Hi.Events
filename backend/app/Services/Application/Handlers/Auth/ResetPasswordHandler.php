<?php

namespace HiEvents\Services\Application\Handlers\Auth;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Mail\User\ResetPasswordSuccess;
use HiEvents\Repository\Interfaces\PasswordResetTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Auth\DTO\ResetPasswordDTO;
use HiEvents\Services\Domain\Auth\PasswordLoginPolicy;
use HiEvents\Services\Domain\Auth\ResetPasswordTokenValidateService;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Hashing\HashManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class ResetPasswordHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        private readonly Mailer $mailer,
        private readonly HashManager $hashManager,
        private readonly DatabaseManager $databaseManager,
        private readonly LoggerInterface $logger,
        private readonly ResetPasswordTokenValidateService $passwordTokenValidateService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly UserSessionRevocationService $sessionRevocationService,
        private readonly UserIdentityService $userIdentityService,
        private readonly PasswordLoginPolicy $passwordLoginPolicy,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(ResetPasswordDTO $resetPasswordData): void
    {
        $this->databaseManager->transaction(function () use ($resetPasswordData) {
            $resetToken = $this->passwordTokenValidateService->validateAndFetchToken($resetPasswordData->token);
            $user = $this->validateUser($resetToken->getEmail());

            if (! $this->passwordLoginPolicy->isAllowedFor($user->getId())) {
                throw new PasswordInvalidException(__('Password sign-in is turned off. Sign in with your organisation\'s sign-in provider instead.'));
            }

            if ($this->checkNewPasswordIsOldPassword($user, $resetPasswordData->password)) {
                throw new PasswordInvalidException(__('New password must be different from the old password.'));
            }

            $this->resetUserPassword($user->getId(), $resetPasswordData->password);
            $this->trustedDeviceService->revokeAll($user->getId());
            $this->sessionRevocationService->revokeAll($user->getId());
            $this->removeIdentitiesFromUnverifiedUser($user);
            $this->deleteResetToken($resetToken->getEmail());
            $this->logResetPasswordSuccess($user);
            $this->sendResetPasswordEmail($user);
        });
    }

    private function checkNewPasswordIsOldPassword(UserDomainObject $user, string $newPassword): bool
    {
        return $user->getPassword() !== null && $this->hashManager->check($newPassword, $user->getPassword());
    }

    private function removeIdentitiesFromUnverifiedUser(UserDomainObject $user): void
    {
        if ($user->getEmailVerifiedAt() === null) {
            $this->userIdentityService->deleteForUser($user->getId());
        }
    }

    private function validateUser(string $email): UserDomainObject
    {
        $user = $this->userRepository->findFirstWhere(['email' => $email]);
        if (! $user) {
            throw new ResourceNotFoundException(__('User not found'));
        }

        return $user;
    }

    private function resetUserPassword(int $userId, string $newPassword): void
    {
        $this->userRepository->updateWhere(
            attributes: [
                'password' => $this->hashManager->make($newPassword),
            ],
            where: [
                'id' => $userId,
            ],
        );
    }

    private function deleteResetToken(string $email): void
    {
        $this->passwordResetTokenRepository->deleteWhere(['email' => $email]);
    }

    private function logResetPasswordSuccess(UserDomainObject $user): void
    {
        $this->logger->info('Password reset successfully', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ]
        );
    }

    private function sendResetPasswordEmail(UserDomainObject $user): void
    {
        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new ResetPasswordSuccess);
    }
}
