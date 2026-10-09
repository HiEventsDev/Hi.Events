<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Mail\User\TwoFactorDisabledMail;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

class TwoFactorResetService
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly DatabaseManager $databaseManager,
        private readonly Mailer $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function reset(UserDomainObject $user, ?int $resetByUserId): void
    {
        if (! $this->twoFactorAuthenticationService->isEnabled($user)
            && ! $this->twoFactorAuthenticationService->hasPendingSetup($user)) {
            throw new ResourceConflictException(__('Two-factor authentication is not enabled for this user.'));
        }

        $this->databaseManager->transaction(function () use ($user) {
            $this->twoFactorAuthenticationService->disable($user->getId());
            $this->trustedDeviceService->revokeAll($user->getId());
        });

        $this->logger->info('Two-factor authentication reset by administrator', [
            'user_id' => $user->getId(),
            'reset_by_user_id' => $resetByUserId,
        ]);

        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new TwoFactorDisabledMail($user, resetByAdministrator: true));
    }
}
