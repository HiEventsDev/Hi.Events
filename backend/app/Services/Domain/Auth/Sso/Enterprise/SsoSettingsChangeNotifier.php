<?php

namespace HiEvents\Services\Domain\Auth\Sso\Enterprise;

use HiEvents\Mail\Account\SsoSettingsChangedMail;
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

    public function notifyOwner(int $accountId, int $actorUserId, string $change): void
    {
        $this->logger->info('SSO settings changed', [
            'account_id' => $accountId,
            'actor_user_id' => $actorUserId,
            'change' => $change,
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
                change: $change,
            ));
    }
}
