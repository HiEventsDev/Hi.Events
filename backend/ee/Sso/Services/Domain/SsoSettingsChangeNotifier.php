<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\Enterprise\Sso\Mail\SsoSettingsChangedMail;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Psr\Log\LoggerInterface;

class SsoSettingsChangeNotifier
{
    public function __construct(
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly Mailer $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array<string, string>  $replace
     */
    public function notifyOwner(int $accountId, int $actorUserId, string $change, array $replace = []): void
    {
        $this->logger->info('SSO settings changed', [
            'account_id' => $accountId,
            'actor_user_id' => $actorUserId,
            'change' => __($change, $replace, 'en'),
        ]);

        $owner = $this->accountUserRepository->findFirstWhere([
            'account_id' => $accountId,
            'is_account_owner' => true,
        ]);

        if ($owner === null || $owner->getUserId() === $actorUserId) {
            return;
        }

        $ownerUser = $this->userRepository->findById($owner->getUserId());

        $this->mailer
            ->to($ownerUser->getEmail())
            ->locale($ownerUser->getLocale())
            ->send(new SsoSettingsChangedMail(
                actorEmail: $this->userRepository->findById($actorUserId)->getEmail(),
                change: __($change, $replace, $ownerUser->getLocale()),
            ));
    }
}
