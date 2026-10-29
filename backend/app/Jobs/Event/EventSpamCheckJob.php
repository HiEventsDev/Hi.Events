<?php

namespace HiEvents\Jobs\Event;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Mail\Admin\EventFlaggedAsSpamMail;
use HiEvents\Mail\Event\EventPendingManualReviewMail;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Services\Domain\Event\DTO\EventSpamCheckResultDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckContentService;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EventSpamCheckJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 600;

    public function __construct(
        private readonly int $eventId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    /**
     * @throws Throwable
     */
    public function handle(
        EventRepositoryInterface $eventRepository,
        EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
        AccountRepositoryInterface $accountRepository,
        EventSpamCheckService $eventSpamCheckService,
        EventSpamCheckContentService $eventSpamCheckContentService,
        Mailer $mailer,
        Repository $config,
        DatabaseManager $databaseManager,
    ): void {
        if (! $eventSpamCheckService->isEnabled()) {
            return;
        }

        $event = $eventSpamCheckContentService->loadEvent($this->eventId);

        if ($event === null || ! in_array($event->getStatus(), EventSpamCheckService::CHECKED_EVENT_STATUSES, true)) {
            return;
        }

        $content = $eventSpamCheckContentService->buildForEvent($event);
        $contentHash = $eventSpamCheckService->hashContent($content);

        $existingCheck = $eventSpamCheckRepository->findFirstWhere([
            'event_id' => $this->eventId,
            'content_hash' => $contentHash,
        ]);

        $vettedStatuses = [EventSpamCheckStatus::CLEAN->name, EventSpamCheckStatus::APPROVED->name];

        if ($existingCheck !== null && in_array($existingCheck->getStatus(), $vettedStatuses, true)) {
            return;
        }

        $wasApprovedByAdmin = $eventSpamCheckRepository->findFirstWhere([
            'event_id' => $this->eventId,
            'status' => EventSpamCheckStatus::APPROVED->name,
        ]) !== null;

        if ($wasApprovedByAdmin && $existingCheck !== null) {
            return;
        }

        $result = $eventSpamCheckService->checkContent($content);

        if (! $result->isSpam) {
            $this->storeCheck($eventSpamCheckRepository, $result, EventSpamCheckStatus::CLEAN, $contentHash);

            return;
        }

        if ($wasApprovedByAdmin) {
            $this->storeCheck($eventSpamCheckRepository, $result, EventSpamCheckStatus::FLAGGED, $contentHash);
            $this->notifySupport($mailer, $config, $accountRepository->findByEventId($event->getId()), $event, $result, eventStillLive: true);

            return;
        }

        $databaseManager->transaction(function () use (
            $eventRepository,
            $eventSpamCheckRepository,
            $accountRepository,
            $mailer,
            $config,
            $event,
            $result,
            $contentHash,
        ) {
            $updated = $eventRepository->updateWhere(
                attributes: ['status' => EventStatus::PENDING_MANUAL_REVIEW->name],
                where: [
                    'id' => $this->eventId,
                    'status' => $event->getStatus(),
                ],
            );

            if ($updated === 0) {
                return;
            }

            $this->storeCheck($eventSpamCheckRepository, $result, EventSpamCheckStatus::FLAGGED, $contentHash, $event->getStatus());

            $account = $accountRepository->findByEventId($event->getId());

            if ($account->getEmail()) {
                $mailer->to($account->getEmail())->send(new EventPendingManualReviewMail($event));
            }

            $this->notifySupport($mailer, $config, $account, $event, $result, eventStillLive: false);
        });
    }

    private function notifySupport(
        Mailer $mailer,
        Repository $config,
        AccountDomainObject $account,
        EventDomainObject $event,
        EventSpamCheckResultDTO $result,
        bool $eventStillLive,
    ): void {
        $supportEmail = $config->get('app.platform_support_email');

        if (! $supportEmail) {
            return;
        }

        $mailer->to($supportEmail)->send(new EventFlaggedAsSpamMail(
            event: $event,
            account: $account,
            verdict: $result->toVerdictArray(),
            eventStillLive: $eventStillLive,
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Event spam check did not complete', [
            'eventId' => $this->eventId,
            'exception' => $exception->getMessage(),
        ]);
    }

    private function storeCheck(
        EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
        EventSpamCheckResultDTO $result,
        EventSpamCheckStatus $status,
        string $contentHash,
        ?string $previousEventStatus = null,
    ): void {
        $eventSpamCheckRepository->create([
            'event_id' => $this->eventId,
            'status' => $status->name,
            'verdict' => $result->toVerdictArray(),
            'content_hash' => $contentHash,
            'checked_at' => now(),
            'previous_event_status' => $previousEventStatus,
        ]);
    }
}
