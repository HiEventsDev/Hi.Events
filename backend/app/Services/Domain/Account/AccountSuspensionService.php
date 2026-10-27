<?php

namespace HiEvents\Services\Domain\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Generated\AccountDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrganizerDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Mail\Account\AccountSuspendedEmail;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Subscription\AccountSubscriptionCancellationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Psr\Log\LoggerInterface;
use Throwable;

class AccountSuspensionService
{
    private const SUSPENDED_CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly LoggerInterface $logger,
        private readonly AccountSubscriptionCancellationService $subscriptionCancellationService,
    ) {}

    public function isAccountSuspended(int $accountId): bool
    {
        return Cache::remember(
            key: $this->getSuspendedCacheKey($accountId),
            ttl: self::SUSPENDED_CACHE_TTL_SECONDS,
            callback: fn () => $this->accountRepository->findFirstWhere([
                AccountDomainObjectAbstract::ID => $accountId,
            ])?->getSuspendedAt() !== null,
        );
    }

    /**
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function suspend(
        int $accountId,
        int $suspendedByUserId,
        ?string $reason,
        bool $notifyAccountOwner,
    ): AccountDomainObject {
        $account = $this->databaseManager->transaction(function () use ($accountId, $reason, $notifyAccountOwner) {
            $account = $this->accountRepository->findById($accountId);

            if ($account->getSuspendedAt() !== null) {
                throw new ResourceConflictException(__('This account is already suspended.'));
            }

            if ($this->accountHasSuperadminMember($accountId)) {
                throw new ResourceConflictException(__('Accounts with a superadmin member cannot be suspended.'));
            }

            $account = $this->accountRepository->updateFromArray($accountId, [
                AccountDomainObjectAbstract::SUSPENDED_AT => now()->toDateTimeString(),
                AccountDomainObjectAbstract::SUSPENSION_REASON => $reason,
            ]);

            $this->eventRepository->updateWhere(
                attributes: [EventDomainObjectAbstract::STATUS => EventStatus::DRAFT->name],
                where: [
                    EventDomainObjectAbstract::ACCOUNT_ID => $accountId,
                    [EventDomainObjectAbstract::STATUS, 'in', [
                        EventStatus::LIVE->name,
                        EventStatus::PENDING_MANUAL_REVIEW->name,
                    ]],
                ],
            );

            $this->organizerRepository->updateWhere(
                attributes: [OrganizerDomainObjectAbstract::STATUS => OrganizerStatus::DRAFT->name],
                where: [
                    OrganizerDomainObjectAbstract::ACCOUNT_ID => $accountId,
                    OrganizerDomainObjectAbstract::STATUS => OrganizerStatus::LIVE->name,
                ],
            );

            if ($notifyAccountOwner) {
                Mail::to($account->getEmail())->queue(new AccountSuspendedEmail($account));
            }

            return $account;
        });

        Cache::forget($this->getSuspendedCacheKey($accountId));

        $this->subscriptionCancellationService->tryCancelAtPeriodEnd($accountId);

        $this->logger->info('Account suspended', [
            'account_id' => $accountId,
            'suspended_by_user_id' => $suspendedByUserId,
            'account_owner_notified' => $notifyAccountOwner,
        ]);

        return $account;
    }

    /**
     * @throws ResourceConflictException
     */
    public function unsuspend(int $accountId, int $unsuspendedByUserId): AccountDomainObject
    {
        $account = $this->accountRepository->findById($accountId);

        if ($account->getSuspendedAt() === null) {
            throw new ResourceConflictException(__('This account is not suspended.'));
        }

        $account = $this->accountRepository->updateFromArray($accountId, [
            AccountDomainObjectAbstract::SUSPENDED_AT => null,
            AccountDomainObjectAbstract::SUSPENSION_REASON => null,
        ]);

        Cache::forget($this->getSuspendedCacheKey($accountId));

        $this->logger->info('Account unsuspended', [
            'account_id' => $accountId,
            'unsuspended_by_user_id' => $unsuspendedByUserId,
        ]);

        return $account;
    }

    private function accountHasSuperadminMember(int $accountId): bool
    {
        return $this->accountUserRepository->countWhere([
            'account_id' => $accountId,
            'role' => Role::SUPERADMIN->name,
        ]) > 0;
    }

    private function getSuspendedCacheKey(int $accountId): string
    {
        return "account:{$accountId}:suspended";
    }
}
