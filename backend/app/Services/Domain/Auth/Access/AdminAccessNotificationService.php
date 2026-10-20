<?php

namespace HiEvents\Services\Domain\Auth\Access;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Mail\Account\AdminAccessGrantedMail;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;

class AdminAccessNotificationService
{
    public function __construct(
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly Mailer $mailer,
    ) {}

    public function notifyOwner(int $accountId, int $newAdminUserId, ?int $grantedByUserId): void
    {
        /** @var AccountUserDomainObject|null $owner */
        $owner = $this->accountUserRepository->findFirstWhere([
            'account_id' => $accountId,
            'is_account_owner' => true,
        ]);

        if ($owner === null || in_array($owner->getUserId(), [$newAdminUserId, $grantedByUserId], true)) {
            return;
        }

        $ownerUser = $this->userRepository->findById($owner->getUserId());

        /** @var AccountUserDomainObject|null $newAdmin */
        $newAdmin = $this->accountUserRepository->findFirstWhere([
            'account_id' => $accountId,
            'user_id' => $newAdminUserId,
        ]);

        $this->mailer
            ->to($ownerUser->getEmail())
            ->locale($ownerUser->getLocale())
            ->send(new AdminAccessGrantedMail(
                newAdminEmail: $this->userRepository->findById($newAdminUserId)->getEmail(),
                grantedByEmail: $grantedByUserId === null ? null : $this->userRepository->findById($grantedByUserId)->getEmail(),
                isInvitation: $newAdmin?->getStatus() === UserStatus::INVITED->name,
            ));
    }
}
