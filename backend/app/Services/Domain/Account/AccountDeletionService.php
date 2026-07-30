<?php

namespace HiEvents\Services\Domain\Account;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\DomainObjects\Enums\AccountDeletionInitiator;
use HiEvents\DomainObjects\Enums\AccountDeletionOutcome;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\AccountDeletionRequestDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Exceptions\AccountDeletionRequestNotFoundException;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Mail\Account\AccountDeletionCancelledEmail;
use HiEvents\Mail\Account\AccountDeletionCompletedEmail;
use HiEvents\Mail\Account\AccountDeletionRequestedEmail;
use HiEvents\Repository\Interfaces\AccountDeletionRequestRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Psr\Log\LoggerInterface;
use Throwable;

class AccountDeletionService
{
    public const GRACE_PERIOD_DAYS = 30;

    private const PENDING_DELETION_CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly AccountDeletionRequestRepositoryInterface $deletionRequestRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AccountAnonymizationService $anonymizationService,
        private readonly AccountHardDeletionService $hardDeletionService,
        private readonly DatabaseManager $databaseManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function findActiveRequest(int $accountId): ?AccountDeletionRequestDomainObject
    {
        return $this->deletionRequestRepository->findFirstWhere([
            AccountDeletionRequestDomainObjectAbstract::ACCOUNT_ID => $accountId,
            AccountDeletionRequestDomainObjectAbstract::STATUS => AccountDeletionRequestStatus::REQUESTED->name,
        ]);
    }

    public function isAccountPendingDeletion(int $accountId): bool
    {
        return Cache::remember(
            key: $this->getPendingDeletionCacheKey($accountId),
            ttl: self::PENDING_DELETION_CACHE_TTL_SECONDS,
            callback: fn () => $this->findActiveRequest($accountId) !== null,
        );
    }

    public function getCannotDeleteReason(int $accountId): ?string
    {
        if ($this->findActiveRequest($accountId) !== null) {
            return __('Account deletion has already been requested.');
        }

        $blockingEvents = $this->eventRepository->getUpcomingEventsWithCompletedOrders($accountId);

        if ($blockingEvents->isNotEmpty()) {
            return __('The following upcoming events have completed orders: :events. Please cancel and refund these orders before deleting your account.', [
                'events' => $blockingEvents
                    ->map(fn (EventDomainObject $event) => $event->getTitle())
                    ->implode(', '),
            ]);
        }

        return null;
    }

    public function determineOutcome(int $accountId): AccountDeletionOutcome
    {
        return $this->orderRepository->accountHasCompletedOrders($accountId)
            ? AccountDeletionOutcome::ANONYMIZE
            : AccountDeletionOutcome::HARD_DELETE;
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function requestDeletion(
        int $accountId,
        int $requestedByUserId,
        AccountDeletionInitiator $initiator,
        ?string $reason = null,
    ): AccountDeletionRequestDomainObject {
        $deletionRequest = $this->databaseManager->transaction(function () use ($accountId, $requestedByUserId, $initiator, $reason) {
            $cannotDeleteReason = $this->getCannotDeleteReason($accountId);

            if ($cannotDeleteReason !== null) {
                throw new CannotDeleteEntityException($cannotDeleteReason);
            }

            /** @var AccountDeletionRequestDomainObject $deletionRequest */
            $deletionRequest = $this->deletionRequestRepository->create([
                AccountDeletionRequestDomainObjectAbstract::ACCOUNT_ID => $accountId,
                AccountDeletionRequestDomainObjectAbstract::REQUESTED_BY_USER_ID => $requestedByUserId,
                AccountDeletionRequestDomainObjectAbstract::INITIATED_BY => $initiator->name,
                AccountDeletionRequestDomainObjectAbstract::REASON => $reason,
                AccountDeletionRequestDomainObjectAbstract::STATUS => AccountDeletionRequestStatus::REQUESTED->name,
                AccountDeletionRequestDomainObjectAbstract::EXPECTED_OUTCOME => $this->determineOutcome($accountId)->name,
                AccountDeletionRequestDomainObjectAbstract::SCHEDULED_DELETION_AT => now()->addDays(self::GRACE_PERIOD_DAYS),
            ]);

            $this->eventRepository->updateWhere(
                attributes: [EventDomainObjectAbstract::STATUS => EventStatus::DRAFT->name],
                where: [
                    EventDomainObjectAbstract::ACCOUNT_ID => $accountId,
                    EventDomainObjectAbstract::STATUS => EventStatus::LIVE->name,
                ],
            );

            $account = $this->accountRepository->findById($accountId);

            Mail::to($account->getEmail())->queue(new AccountDeletionRequestedEmail($account, $deletionRequest));

            return $deletionRequest;
        });

        Cache::forget($this->getPendingDeletionCacheKey($accountId));

        $this->logger->info('Account deletion requested', [
            'account_id' => $accountId,
            'requested_by_user_id' => $requestedByUserId,
            'initiated_by' => $initiator->name,
            'scheduled_deletion_at' => $deletionRequest->getScheduledDeletionAt(),
            'expected_outcome' => $deletionRequest->getExpectedOutcome(),
        ]);

        return $deletionRequest;
    }

    /**
     * @throws AccountDeletionRequestNotFoundException
     * @throws Throwable
     */
    public function cancelDeletion(int $accountId, int $cancelledByUserId): AccountDeletionRequestDomainObject
    {
        $deletionRequest = $this->databaseManager->transaction(function () use ($accountId, $cancelledByUserId) {
            $activeRequest = $this->findActiveRequest($accountId);

            if ($activeRequest === null) {
                throw new AccountDeletionRequestNotFoundException(
                    __('There is no pending deletion request for this account.'),
                );
            }

            $cancelledRequest = $this->deletionRequestRepository->updateFromArray($activeRequest->getId(), [
                AccountDeletionRequestDomainObjectAbstract::STATUS => AccountDeletionRequestStatus::CANCELLED->name,
                AccountDeletionRequestDomainObjectAbstract::CANCELLED_AT => now(),
                AccountDeletionRequestDomainObjectAbstract::CANCELLED_BY_USER_ID => $cancelledByUserId,
            ]);

            $account = $this->accountRepository->findById($accountId);

            Mail::to($account->getEmail())->queue(new AccountDeletionCancelledEmail($account));

            return $cancelledRequest;
        });

        Cache::forget($this->getPendingDeletionCacheKey($accountId));

        $this->logger->info('Account deletion cancelled', [
            'account_id' => $accountId,
            'cancelled_by_user_id' => $cancelledByUserId,
        ]);

        return $deletionRequest;
    }

    /**
     * @throws Throwable
     */
    public function executeDeletion(int $deletionRequestId): void
    {
        /** @var AccountDeletionRequestDomainObject $deletionRequest */
        $deletionRequest = $this->deletionRequestRepository->findById($deletionRequestId);

        if ($deletionRequest->getStatus() !== AccountDeletionRequestStatus::REQUESTED->name) {
            $this->logger->info('Skipping account deletion execution for inactive request', [
                'deletion_request_id' => $deletionRequestId,
                'status' => $deletionRequest->getStatus(),
            ]);

            return;
        }

        $accountId = $deletionRequest->getAccountId();
        $account = $this->accountRepository->findById($accountId);
        $recipientEmail = $account->getEmail();
        $recipientName = $account->getName();

        $outcome = $this->determineOutcome($accountId);

        $manifest = $outcome === AccountDeletionOutcome::HARD_DELETE
            ? $this->hardDeletionService->deleteAccount($accountId)
            : $this->anonymizationService->anonymizeAccount($accountId);

        $this->deletionRequestRepository->updateFromArray($deletionRequestId, [
            AccountDeletionRequestDomainObjectAbstract::STATUS => AccountDeletionRequestStatus::COMPLETED->name,
            AccountDeletionRequestDomainObjectAbstract::OUTCOME => $outcome->name,
            AccountDeletionRequestDomainObjectAbstract::COMPLETED_AT => now(),
            AccountDeletionRequestDomainObjectAbstract::DELETION_MANIFEST => $manifest,
        ]);

        Cache::forget($this->getPendingDeletionCacheKey($accountId));

        Mail::to($recipientEmail)->queue(new AccountDeletionCompletedEmail(
            accountName: $recipientName,
            wasAnonymized: $outcome === AccountDeletionOutcome::ANONYMIZE,
        ));

        $this->logger->info('Account deletion executed', [
            'account_id' => $accountId,
            'deletion_request_id' => $deletionRequestId,
            'outcome' => $outcome->name,
        ]);
    }

    private function getPendingDeletionCacheKey(int $accountId): string
    {
        return "account:{$accountId}:pending-deletion";
    }
}
