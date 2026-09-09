<?php

namespace HiEvents\Jobs\Event;

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

        if ($event === null || $event->getStatus() !== EventStatus::LIVE->name) {
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

        $result = $eventSpamCheckService->checkContent($content);

        if (! $result->isSpam) {
            $this->storeCheck($eventSpamCheckRepository, $result, EventSpamCheckStatus::CLEAN, $contentHash);

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
                    'status' => EventStatus::LIVE->name,
                ],
            );

            if ($updated === 0) {
                return;
            }

            $this->storeCheck($eventSpamCheckRepository, $result, EventSpamCheckStatus::FLAGGED, $contentHash);

            $organizerEmail = $event->getOrganizer()?->getEmail();

            if ($organizerEmail) {
                $mailer->to($organizerEmail)->send(new EventPendingManualReviewMail($event));
            }

            $supportEmail = $config->get('app.platform_support_email');

            if ($supportEmail) {
                $account = $accountRepository->findByEventId($event->getId());

                $mailer->to($supportEmail)->send(
                    new EventFlaggedAsSpamMail($event, $account, $result->toVerdictArray())
                );
            }
        });
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
    ): void {
        $eventSpamCheckRepository->create([
            'event_id' => $this->eventId,
            'status' => $status->name,
            'verdict' => $result->toVerdictArray(),
            'content_hash' => $contentHash,
            'checked_at' => now(),
        ]);
    }
}
