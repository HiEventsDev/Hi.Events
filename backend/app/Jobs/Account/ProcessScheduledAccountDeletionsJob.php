<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Account;

use Carbon\Carbon;
use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\DomainObjects\Generated\AccountDeletionRequestDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AccountDeletionRequestStatus;
use HiEvents\Mail\Account\AccountDeletionReminderEmail;
use HiEvents\Repository\Interfaces\AccountDeletionRequestRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessScheduledAccountDeletionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const REMINDER_DAYS_BEFORE_DELETION = 7;

    public function handle(
        AccountDeletionRequestRepositoryInterface $deletionRequestRepository,
        AccountRepositoryInterface $accountRepository,
    ): void {
        $this->sendReminders($deletionRequestRepository, $accountRepository);
        $this->dispatchDueDeletions($deletionRequestRepository);
    }

    private function sendReminders(
        AccountDeletionRequestRepositoryInterface $deletionRequestRepository,
        AccountRepositoryInterface $accountRepository,
    ): void {
        $dueForReminder = $deletionRequestRepository->findDueForReminder(self::REMINDER_DAYS_BEFORE_DELETION);

        /** @var AccountDeletionRequestDomainObject $deletionRequest */
        foreach ($dueForReminder as $deletionRequest) {
            try {
                $account = $accountRepository->findById($deletionRequest->getAccountId());

                Mail::to($account->getEmail())->queue(new AccountDeletionReminderEmail(
                    accountName: $account->getName(),
                    scheduledDeletionDate: Carbon::parse($deletionRequest->getScheduledDeletionAt())
                        ->setTimezone($account->getTimezone() ?? 'UTC')
                        ->toFormattedDateString(),
                ));

                $deletionRequestRepository->updateFromArray($deletionRequest->getId(), [
                    AccountDeletionRequestDomainObjectAbstract::REMINDER_SENT_AT => now(),
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to send account deletion reminder', [
                    'deletion_request_id' => $deletionRequest->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function dispatchDueDeletions(
        AccountDeletionRequestRepositoryInterface $deletionRequestRepository,
    ): void {
        $dueRequests = $deletionRequestRepository->findWhere([
            AccountDeletionRequestDomainObjectAbstract::STATUS => AccountDeletionRequestStatus::REQUESTED->name,
            [AccountDeletionRequestDomainObjectAbstract::SCHEDULED_DELETION_AT, '<=', Carbon::now()->toDateTimeString()],
        ]);

        /** @var AccountDeletionRequestDomainObject $deletionRequest */
        foreach ($dueRequests as $deletionRequest) {
            ExecuteAccountDeletionJob::dispatch($deletionRequest->getId());
        }
    }
}
